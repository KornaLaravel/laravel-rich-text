<?php

namespace Tonysm\RichTextLaravel\Tests;

use Tonysm\RichTextLaravel\Attachables\MissingAttachable;
use Tonysm\RichTextLaravel\Attachables\RemoteImage;
use Tonysm\RichTextLaravel\Attachment;
use Tonysm\RichTextLaravel\Content;
use Tonysm\RichTextLaravel\Tests\Stubs\User;

class ContentTest extends TestCase
{
    /** @test */
    public function equality()
    {
        $html = "<div>test</div>";
        $content = $this->fromHtml($html);

        $this->assertStringContainsString($html, $content->render());
    }

    /** @test */
    public function serializes()
    {
        $content = $this->fromHtml("Hello!");
        $this->assertEquals($content->render(), unserialize(serialize($content))->render());
    }

    /** @test */
    public function keeps_newlines_consistent()
    {
        $html = "<div>a<br></div>";
        $content = $this->fromHtml($html);

        $this->assertStringContainsString($html, $content->render());
    }

    /** @test */
    public function extracts_links()
    {
        $html = '<a href="http://example.com/1">first link</a><br><a href="http://example.com/1">second link</a>';
        $content = $this->fromHtml($html);

        $this->assertEquals(['http://example.com/1'], $content->links());
    }

    /** @test */
    public function extracts_attachables()
    {
        $attachable = User::create(['name' => 'Jon Doe']);
        $sgid = $attachable->richTextSgid();

        $html = <<<HTML
        <rich-text-attachment sgid="$sgid" caption="Captioned"></rich-text-attachment>
        HTML;

        $content = $this->fromHtml($html);

        $this->assertCount(1, $content->attachments());

        $attachment = $content->attachments()->first();

        $this->assertEquals("Captioned", $attachment->caption());
        $this->assertTrue($attachment->attachable->is($attachable));
    }

    /** @test */
    public function extracts_remote_image_attachables()
    {
        $html = <<<HTML
        <rich-text-attachment content-type="image" url="http://example.com/cat.jpg" width="200" height="100" caption="Captioned"></rich-text-attachment>
        HTML;
        $content = $this->fromHtml($html);

        $this->assertCount(1, $content->attachments());

        $attachment = $content->attachments()->first();
        $this->assertEquals('Captioned', $attachment->caption());

        $attachable = $attachment->attachable;
        $this->assertInstanceOf(RemoteImage::class, $attachable);
        $this->assertEquals('http://example.com/cat.jpg', $attachable->url);
        $this->assertEquals('200', $attachable->width);
        $this->assertEquals('100', $attachable->height);
    }

    /** @test */
    public function handles_destryed_attachables_as_missing()
    {
        $attachable = User::create(['name' => 'Jon Doe']);
        $sgid = $attachable->richTextSgid();
        $html = <<<HTML
        <rich-text-attachment sgid="$sgid" caption="User mention"></rich-text-attachment>
        HTML;

        $attachable->delete();

        $content = $this->fromHtml($html);

        $this->assertCount(1, $content->attachments());
        $this->assertInstanceOf(MissingAttachable::class, $content->attachments()->first()->attachable);
    }

    /** @test */
    public function extracts_missing_attachables()
    {
        $html = <<<HTML
        <rich-text-attachment sgid="missing" caption="Captioned"></rich-text-attachment>
        HTML;

        $content = $this->fromHtml($html);

        $this->assertCount(1, $content->attachments());
        $this->assertInstanceOf(MissingAttachable::class, $content->attachments()->first()->attachable);
    }

    /** @test */
    public function converts_trix_formatted_attachments()
    {
        $html = <<<HTML
        <figure
            data-trix-attachment='{"sgid": "123", "contentType": "text/plain", "width": 200, "height": 100}'
            data-trix-attributes='{"caption": "Captioned"}'
        ></figure>
        HTML;

        $content = $this->fromHtml($html);

        $this->assertCount(1, $content->attachments());

        $this->assertStringContainsString('<rich-text-attachment sgid="123" content-type="text/plain" width="200" height="100" caption="Captioned"></rich-text-attachment>', $content->render());
    }

    /** @test */
    public function converts_trix_formatetd_attachments_with_custom_tag_name()
    {
        $this->withAttachmentTagName('arbitrary-tag', function () {
            $html = <<<HTML
            <figure
                data-trix-attachment='{"sgid": "123", "contentType": "text/plain", "width": 200, "height": 100}'
                data-trix-attributes='{"caption": "Captioned"}'
            ></figure>
            HTML;

            $content = $this->fromHtml($html);

            $this->assertCount(1, $content->attachments());

            $this->assertStringContainsString('<arbitrary-tag sgid="123" content-type="text/plain" width="200" height="100" caption="Captioned"></arbitrary-tag>', $content->render());
        });
    }

    private function withAttachmentTagName(string $tagName, callable $callback)
    {
        try {
            $oldTagName = Attachment::$TAG_NAME;
            Attachment::useTagName($tagName);
            $callback();
        } finally {
            Attachment::useTagName($oldTagName);
        }
    }

    private function fromHtml(string $html): Content
    {
        return tap(new Content($html), fn ($content) => $this->assertNotEmpty($content->render()));
    }
}
