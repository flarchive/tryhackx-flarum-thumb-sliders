import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import ThumbSlider from './components/ThumbSlider';

app.initializers.add('tryhackx-thumb-sliders', () => {
  // Extend contentItems to add the thumbnail slider on the left
  extend(DiscussionListItem.prototype, 'contentItems', function (items) {
    // Check if extension is enabled
    const enabled = app.forum.attribute('thumbSlidersEnabled');
    if (enabled === false) return;

    const discussion = this.attrs.discussion;
    const images = discussion.attribute('thumbImages');
    const hasImages = images && Array.isArray(images) && images.length > 0;

    const fallbackMode = app.forum.attribute('thumbSlidersFallbackMode') || 'none';
    const fallbackUrl = app.forum.attribute('thumbSlidersFallbackImageUrl') || '';

    // Decide whether to render anything when there are no extracted images
    if (!hasImages) {
      if (fallbackMode === 'none') return;
      if (fallbackMode === 'custom' && !fallbackUrl) return; // no uploaded file selected
    }

    const sliderWidth = parseInt(app.forum.attribute('thumbSlidersSliderWidth')) || 130;
    const autoplaySpeed = parseInt(app.forum.attribute('thumbSlidersAutoplaySpeed')) || 1200;

    items.add(
      'thumbSlider',
      <ThumbSlider
        images={hasImages ? images : []}
        sliderWidth={sliderWidth}
        autoplaySpeed={autoplaySpeed}
        fallbackMode={fallbackMode}
        fallbackUrl={fallbackUrl}
      />,
      110 // Higher priority than authorAvatar (100) so it renders first (leftmost)
    );
  });
});
