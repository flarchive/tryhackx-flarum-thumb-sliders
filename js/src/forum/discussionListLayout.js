/**
 * SHARED DISCUSSION-LIST LAYOUT MODULE
 * ------------------------------------------------------------------
 * Identical copy lives in BOTH:
 *   - tryhackx/flarum-thumb-sliders/js/src/forum/discussionListLayout.js
 *   - tryhackx/flarum-topic-rating/js/src/forum/discussionListLayout.js
 * Keep them in sync. Bump LAYOUT_VERSION when the behaviour changes.
 *
 * Purpose: replace the fragile absolute-positioning CSS lattice in the
 * DiscussionListItem with a single, semantic flex/grid structure that the
 * three coordinated features (THUMBS / RATINGS / VIEWS) and tags drop into.
 *
 * It overrides `contentView()` (NOT the whole `view()`), so slidable and the
 * controls dropdown keep working untouched. Inside, it still calls the normal
 * ItemList builders (`contentItems()`, `infoItems()`, `statsItems()`) so that
 * foreign injectors keep working:
 *   - VIEWS (fof/discussion-views) -> infoItems 'discussion-views'
 *   - tags  (flarum/tags)          -> infoItems 'tags'
 *   - THUMBS                       -> contentItems 'thumbSlider'
 *   - RATINGS                      -> infoItems 'rating'
 * Detection = presence of the key in the ItemList, so every combination of the
 * trio is handled automatically. When none of the coordinated keys are present
 * we fall back to the core layout.
 *
 * `.DiscussionListItem-main` is preserved verbatim because flarum-magnet-link
 * reads it from the DOM (hover tooltip).
 *
 * TAGS PLACEMENT (responsive)
 * ---------------------------
 * On tablet/desktop the tags sit in the right-hand meta column (stacked above
 * rating / views / replies). On phones that column is far too narrow, so a
 * discussion with many tags balloons the row height (each tag wraps onto its
 * own line). On phones we therefore render the tags INSIDE
 * `.DiscussionListItem-main`, on their own line right below the author/info
 * line, where they can flex-wrap across the full width of the row. The actual
 * wrapping is done in less/forum.less (`.DiscussionListItem-mobileTags`).
 *
 * Since the breakpoint is evaluated in JS at render time (not via CSS), we also
 * register a one-shot matchMedia listener that triggers a redraw when the
 * viewport crosses the phone breakpoint, so the markup stays correct on resize
 * / orientation change.
 */

import app from 'flarum/forum/app';
import { override } from 'flarum/common/extend';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import Link from 'flarum/common/components/Link';
import listItems from 'flarum/common/helpers/listItems';
import highlight from 'flarum/common/helpers/highlight';
import classList from 'flarum/common/utils/classList';

const LAYOUT_VERSION = 5;

// Must mirror the `@phone` breakpoint in core's less variables (and the
// `@media @phone` blocks in our forum.less): max-width 767.98px.
const PHONE_QUERY = '(max-width: 767.98px)';

export default function installDiscussionListLayout() {
  // Idempotent shared registry, coordinated across every TryHackX extension that
  // ships this module. Defend against a third-party script having clobbered the
  // global with a non-object (which would make the install guard below throw or
  // silently no-op) by resetting to a fresh object. We keep the existing
  // `installed` / `version` protocol so mixed extension versions (one updated,
  // one not yet) still coordinate correctly.
  let registry = window.tryhackxDLL;
  if (!registry || typeof registry !== 'object') {
    registry = window.tryhackxDLL = {};
  }

  // Whichever extension (THUMBS / RATINGS / …) initializes first installs the
  // single override; the others no-op. Render-time detection (below) handles
  // which features are actually active.
  if (registry.installed) {
    // Already installed by another TryHackX extension. If the installed copy's
    // version differs from ours, the byte-identical copies have DRIFTED (a
    // partial upgrade) — warn instead of silently rendering with the older
    // layout. Same version => nothing to do.
    if (registry.version !== LAYOUT_VERSION && typeof console !== 'undefined') {
      console.warn(
        '[tryhackx] discussion-list layout v' + registry.version +
          ' was already installed by another TryHackX extension, but this build ships v' +
          LAYOUT_VERSION + '. Update all TryHackX extensions to the same version to avoid layout glitches.'
      );
    }
    return;
  }
  registry.installed = true;
  registry.version = LAYOUT_VERSION;

  // Redraw when the viewport crosses the phone breakpoint so the tags hop
  // between the meta column (desktop) and the main column (phone). Registered
  // once, guarded by the same `installed` flag above. The redraw alone is not
  // enough — DiscussionListItem's SubtreeRetainer short-circuits re-renders, so
  // we ALSO force a rebuild from onbeforeupdate (below) when the breakpoint
  // actually flips (e.g. phone rotated into landscape past 768px).
  try {
    const mq = window.matchMedia(PHONE_QUERY);
    const onChange = () => m.redraw();
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else if (mq.addListener) mq.addListener(onChange); // Safari < 14 / legacy
  } catch (e) {
    // matchMedia unavailable -> just skip live re-layout on resize.
  }

  // SubtreeRetainer keeps each row from re-rendering unless the discussion's
  // freshness (or read state) changes. That optimization would otherwise pin a
  // row to whichever layout (meta-column vs main-column tags) it first rendered
  // with, so a viewport that crosses the phone breakpoint after mount wouldn't
  // re-flow. Force a rebuild on the render where the breakpoint differs from the
  // value contentView last rendered with.
  //
  // IMPORTANT: the source of truth for "what did we last render" is set in
  // contentView (`this._tryhackxLastPhone`), NOT here. onbeforeupdate is never
  // called on mount — only on updates — so if we initialized the flag here it
  // would capture the *post-flip* breakpoint and silently swallow the very first
  // desktop→phone (or phone→desktop) switch after load. contentView runs on
  // every render including mount, so the flag always reflects the live DOM.
  //
  // NOTE: must be `override` (not `extend`) — `extend` discards the callback's
  // return value, and onbeforeupdate's boolean return is what drives the skip.
  override(DiscussionListItem.prototype, 'onbeforeupdate', function (original, vnode) {
    const shouldUpdate = original ? original(vnode) : undefined;

    let isPhone;
    try {
      isPhone = window.matchMedia(PHONE_QUERY).matches;
    } catch (e) {
      return shouldUpdate;
    }

    // Breakpoint changed since the last render -> override the SubtreeRetainer
    // skip so contentView re-runs and moves the tags. contentView then updates
    // _tryhackxLastPhone to match.
    if (this._tryhackxLastPhone !== undefined && this._tryhackxLastPhone !== isPhone) {
      return true;
    }
    return shouldUpdate;
  });

  override(DiscussionListItem.prototype, 'contentView', function (original) {
    const contentItems = this.contentItems();
    const infoItems = this.infoItems();

    const thumb = contentItems.has('thumbSlider') ? contentItems.get('thumbSlider') : null;
    const rating = infoItems.has('rating') ? infoItems.get('rating') : null;
    const views = infoItems.has('discussion-views') ? infoItems.get('discussion-views') : null;
    const tags = infoItems.has('tags') ? infoItems.get('tags') : null;

    const discussion = this.attrs.discussion;

    // Phone breakpoint is evaluated in JS (see header). Record it on EVERY
    // render (incl. mount and the stock-layout path below) so onbeforeupdate
    // can force a rebuild whenever the viewport crosses the breakpoint — needed
    // not only for tag placement but also for the per-device avatar hiding and
    // rating placement, which can flip a row between the stock and restructured
    // layouts. Set here — on every render — so it never drifts from the DOM.
    const isPhone = window.matchMedia(PHONE_QUERY).matches;
    this._tryhackxLastPhone = isPhone;

    // Per-device avatar display mode, shared with flarum-thumb-sliders through
    // the neutral `tryhackx-avatars.*` settings (serialized to these attributes
    // by whichever extension is installed; falls back to 'show'). Modes:
    //   'show'       -> always keep the author avatar.
    //   'with_image' -> hide it only when the discussion has a real extracted
    //                   image (a thumb replaces it; fallbacks keep the avatar).
    //   'always'     -> hide it whenever a thumb (incl. fallback) is shown; if
    //                   there is no thumb at all keep the avatar so the row is
    //                   never left empty.
    //   'hide'       -> never show the avatar. A thumb (if any) stands in for
    //                   it; otherwise the row simply has none — the lighter
    //                   list topic-rating users asked for.
    // With thumb-sliders absent there is never a thumb, so only 'show'/'hide'
    // have a visible effect — i.e. the replace modes self-detect thumbs.
    const avatarMode = (isPhone
      ? app.forum.attribute('tryhackxAvatarModeMobile')
      : app.forum.attribute('tryhackxAvatarModeDesktop')) || 'show';
    const thumbImages = discussion.attribute('thumbImages');
    const hasRealImage = Array.isArray(thumbImages) && thumbImages.length > 0;
    let showAvatar = true;
    if (avatarMode === 'hide') showAvatar = false;
    else if (avatarMode === 'always') showAvatar = !thumb;
    else if (avatarMode === 'with_image') showAvatar = !hasRealImage;

    // Per-device discussion-list rating style (topic-rating). The `_title`
    // variants move the rating from the right meta column to the end of the
    // title; topic-rating's StarRating renders the matching compact/full form.
    // Attributes are absent when topic-rating isn't installed (and then there
    // is no rating item anyway), so this safely no-ops there.
    const ratingStyle = (isPhone
      ? app.forum.attribute('tryhackxTopicRatingListStyleMobile')
      : app.forum.attribute('tryhackxTopicRatingListStyleDesktop')) || 'full_meta';
    const ratingAfterTitle = !!rating && /_title$/.test(ratingStyle);

    // Nothing for us to relocate AND the avatar is staying -> keep the stock
    // layout. ('hide' is the only mode that can drop the avatar with none of
    // the coordinated items present, so it is what pulls an otherwise-plain row
    // into the restructured layout.)
    if (!thumb && !rating && !views && !tags && showAvatar) {
      return original();
    }

    // Everything that stays under the title (terminalPost / search excerpt).
    infoItems.remove('rating');
    infoItems.remove('discussion-views');
    infoItems.remove('tags');

    const isUnread = discussion.isUnread();
    const isRead = discussion.isRead();

    // getJumpTo() also sets this.highlightRegExp, consumed by highlight() and
    // by the search excerpt inside infoItems, so it must run first.
    const jumpTo = this.getJumpTo();

    // Right meta column, stacked top-to-bottom: tags, rating, views, replies.
    // On phones the tags are pulled out and rendered in the main column below.
    const meta = [];
    if (tags && !isPhone) meta.push(<div className="DiscussionListItem-meta-item DiscussionListItem-meta-item--tags">{tags}</div>);
    if (rating && !ratingAfterTitle) meta.push(<div className="DiscussionListItem-meta-item DiscussionListItem-meta-item--rating">{rating}</div>);
    if (views) meta.push(<div className="DiscussionListItem-meta-item DiscussionListItem-meta-item--views">{views}</div>);
    this.statsItems()
      .toArray()
      .forEach((stat) => {
        meta.push(<div className="DiscussionListItem-meta-item DiscussionListItem-meta-item--stat">{stat}</div>);
      });

    return (
      <div
        className={classList('DiscussionListItem-content', 'Slidable-content', 'DiscussionListItem-content--restructured', {
          unread: isUnread,
          read: isRead,
        })}
      >
        {thumb}
        {showAvatar ? this.authorView() : null}
        <Link href={app.route.discussion(discussion, jumpTo)} className="DiscussionListItem-main">
          <h2 className="DiscussionListItem-title">
            {highlight(discussion.title(), this.highlightRegExp)}
            {rating && ratingAfterTitle ? <span className="DiscussionListItem-titleRating">{rating}</span> : null}
          </h2>
          <ul className="DiscussionListItem-info">{listItems(infoItems.toArray())}</ul>
          {isPhone && tags ? <div className="DiscussionListItem-mobileTags">{tags}</div> : null}
        </Link>
        <div className="DiscussionListItem-meta">{meta}</div>
      </div>
    );
  });
}
