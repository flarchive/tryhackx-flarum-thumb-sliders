<?php

namespace TryHackX\ThumbSliders\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\Guest;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use TryHackX\ThumbSliders\Api\DiscussionThumbFields;

class ThumbImagesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    const IMG = 'https://example.com/poster.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('tryhackx-thumb-sliders');

        $img = '<r><IMG src="' . self::IMG . '"><s>[img]</s>' . self::IMG . '<e>[/img]</e></IMG></r>';

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
            ],
            Discussion::class => [
                // 1: a normal discussion whose opening post has an image.
                ['id' => 1, 'title' => 'With image', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()->subDays(5)],
                // 2: plain-text opening post.
                ['id' => 2, 'title' => 'Text only', 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()->subDays(4)],
                // 3: opening post hidden by a moderator, discussion still listed.
                ['id' => 3, 'title' => 'Hidden opening post', 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'created_at' => Carbon::now()->subDays(3)],
                // 4: image below the minimum size ([img width=20 height=20]).
                ['id' => 4, 'title' => 'Tiny image', 'user_id' => 2, 'first_post_id' => 4, 'comment_count' => 1, 'created_at' => Carbon::now()->subDays(2)],
                // 5: opening post still private (e.g. held for approval).
                ['id' => 5, 'title' => 'Private opening post', 'user_id' => 1, 'first_post_id' => 5, 'comment_count' => 1, 'created_at' => Carbon::now()->subDays(1)],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => $img],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t>Just text</t>'],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => $img, 'hidden_at' => Carbon::now(), 'hidden_user_id' => 1],
                ['id' => 4, 'discussion_id' => 4, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<r><IMG src="https://example.com/tiny.jpg" width="20" height="20"><s>[img width=20 height=20]</s>https://example.com/tiny.jpg<e>[/img]</e></IMG></r>'],
                ['id' => 5, 'discussion_id' => 5, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => $img, 'is_private' => true],
            ],
        ]);
    }

    /**
     * @return array<string, array> discussion id => attributes
     */
    protected function listDiscussions(?int $actorId = null): array
    {
        $response = $this->send(
            $this->request('GET', '/api/discussions', $actorId ? ['authenticatedAs' => $actorId] : [])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $body = json_decode((string) $response->getBody(), true);

        $byId = [];
        foreach ($body['data'] as $row) {
            $byId[$row['id']] = $row['attributes'];
        }

        return $byId;
    }

    #[Test]
    public function discussion_list_carries_thumb_images(): void
    {
        $rows = $this->listDiscussions();

        $this->assertSame([self::IMG], $rows['1']['thumbImages']);
        $this->assertSame([], $rows['2']['thumbImages']);
    }

    #[Test]
    public function size_limits_apply_to_images_with_explicit_dimensions(): void
    {
        $rows = $this->listDiscussions();

        $this->assertSame([], $rows['4']['thumbImages']);
    }

    #[Test]
    public function nothing_is_extracted_when_the_slider_is_disabled(): void
    {
        $this->setting('tryhackx-thumb-sliders.enabled', '0');

        $rows = $this->listDiscussions();

        $this->assertSame([], $rows['1']['thumbImages']);
    }

    #[Test]
    public function images_of_a_hidden_opening_post_are_not_listed_to_users_who_cannot_see_it(): void
    {
        $this->assertSame([], $this->listDiscussions()['3']['thumbImages'] ?? []);
        $this->assertSame([], $this->listDiscussions(2)['3']['thumbImages'] ?? []);

        // An admin can see the hidden post, so the thumbnail stays.
        $this->assertSame([self::IMG], $this->listDiscussions(1)['3']['thumbImages']);
    }

    #[Test]
    public function visibility_is_checked_even_when_first_post_was_loaded_without_scoping(): void
    {
        // Flarum 2.0 pre-releases (rc.x) eager-load `firstPost` WITHOUT applying
        // post visibility scopes; reproduce that by loading it unscoped.
        $this->app();
        $fields = $this->app()->getContainer()->make(DiscussionThumbFields::class);

        $hidden = Discussion::with('firstPost')->findOrFail(3);
        $private = Discussion::with('firstPost')->findOrFail(5);
        $normal = Discussion::with('firstPost')->findOrFail(1);

        $guest = new Guest();
        $member = User::findOrFail(2);
        $admin = User::findOrFail(1);

        $this->assertSame([], $fields->imagesFor($hidden, $guest));
        $this->assertSame([], $fields->imagesFor($hidden, $member));
        $this->assertSame([self::IMG], $fields->imagesFor($hidden, $admin));

        $this->assertSame([], $fields->imagesFor($private, $guest));
        $this->assertSame([], $fields->imagesFor($private, $member));

        $this->assertSame([self::IMG], $fields->imagesFor($normal, $guest));
    }

    #[Test]
    public function show_endpoint_still_carries_thumb_images(): void
    {
        $response = $this->send($this->request('GET', '/api/discussions/1'));

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame([self::IMG], $body['data']['attributes']['thumbImages']);
    }

    #[Test]
    public function create_endpoint_still_carries_thumb_images(): void
    {
        // Core includes firstPost on Create, so the new discussion arrives in
        // the frontend store with the attribute (and can show a thumb at once).
        $response = $this->send(
            // As the admin: fixture posts are "now", so a member would hit flood control.
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 1,
                'json' => ['data' => ['type' => 'discussions', 'attributes' => ['title' => 'Brand new', 'content' => 'Hello there, world']]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('thumbImages', $body['data']['attributes']);
        $this->assertSame([], $body['data']['attributes']['thumbImages']);
    }

    #[Test]
    public function included_discussions_do_not_lazy_load_first_post_per_row(): void
    {
        // Eight discussions, each with an opening post plus a reply by user 2,
        // listed through the posts endpoint with the discussion included. The
        // attribute used to lazy-load firstPost once per discussion here (an
        // N+1, caught by flarum/testing's repeated-query detector); it is now
        // simply omitted where firstPost is not already loaded.
        $discussions = [];
        $posts = [];
        for ($i = 0; $i < 8; $i++) {
            $id = 100 + $i;
            $discussions[] = ['id' => $id, 'title' => "Bulk $i", 'user_id' => 2, 'first_post_id' => $id * 10, 'comment_count' => 2, 'created_at' => Carbon::now()->subHours($i + 1)];
            $posts[] = ['id' => $id * 10, 'discussion_id' => $id, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<r><IMG src="https://example.com/' . $i . '.jpg"/></r>'];
            $posts[] = ['id' => $id * 10 + 1, 'discussion_id' => $id, 'number' => 2, 'user_id' => 2, 'type' => 'comment', 'content' => '<t>reply</t>'];
        }
        $this->prepareDatabase([Discussion::class => $discussions, Post::class => $posts]);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['author' => 'normal'], 'include' => 'discussion'])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);

        $included = array_filter($body['included'] ?? [], fn ($r) => $r['type'] === 'discussions');
        $this->assertNotEmpty($included);
        foreach ($included as $discussion) {
            $this->assertArrayNotHasKey('thumbImages', $discussion['attributes'] ?? []);
        }
    }

    #[Test]
    public function discussion_list_has_no_n_plus_one(): void
    {
        $discussions = [];
        $posts = [];
        for ($i = 0; $i < 10; $i++) {
            $id = 200 + $i;
            $discussions[] = ['id' => $id, 'title' => "Listed $i", 'user_id' => 2, 'first_post_id' => $id * 10, 'comment_count' => 1, 'created_at' => Carbon::now()->subMinutes($i + 1)];
            $posts[] = ['id' => $id * 10, 'discussion_id' => $id, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<r><IMG src="https://example.com/l' . $i . '.jpg"/></r>'];
        }
        $this->prepareDatabase([Discussion::class => $discussions, Post::class => $posts]);

        // The repeated-query detector fails the test on an N+1.
        $rows = $this->listDiscussions();

        $this->assertSame(['https://example.com/l0.jpg'], $rows['200']['thumbImages']);
    }
}
