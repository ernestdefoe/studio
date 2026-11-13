<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio\Tests\unit;

use ErnestDefoe\Studio\Covers;
use ErnestDefoe\Studio\MeasureCover;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CoversTest extends TestCase
{
    #[Test]
    public function it_finds_markdown_bbcode_and_upload_pictures_in_order()
    {
        $xml = '<r><p>Hi <IMG alt="a" src="https://example.com/a.png?x=1&amp;y=2"><s>![a](</s>https://example.com/a.png?x=1&amp;y=2<e>)</e></IMG></p>'
            .'<p><UPL-IMAGE-PREVIEW url="https://cdn.example.com/b.jpg" uuid="abc-123" image_width="1200" image_height="800">[upl-image-preview]</UPL-IMAGE-PREVIEW></p>'
            .'<p><img src="/assets/c.webp" width="400" height="600"></p></r>';

        $pictures = Covers::pictures($xml);

        $this->assertSame(['https://example.com/a.png?x=1&y=2', 'https://cdn.example.com/b.jpg', '/assets/c.webp'], array_column($pictures, 'src'));
        $this->assertSame([null, 'abc-123', null], array_column($pictures, 'uuid'));
        $this->assertSame(667, Covers::ratio($pictures[1]['width'], $pictures[1]['height']));
        $this->assertSame(1500, Covers::ratio($pictures[2]['width'], $pictures[2]['height']));
    }

    #[Test]
    public function a_picture_inside_a_spoiler_is_never_a_cover()
    {
        $xml = '<r><SPOILER><p><IMG src="https://example.com/secret.png">x</IMG></p></SPOILER><p><ISPOILER><IMG src="https://example.com/inline.png">x</IMG></ISPOILER></p><DETAILS><IMG src="https://example.com/d.png">x</IMG></DETAILS></r>';

        $this->assertSame([], Covers::pictures($xml));
    }

    #[Test]
    public function only_safe_addresses_get_through()
    {
        foreach (['javascript:alert(1)', '//evil.example/x.png', 'data:image/png;base64,xx', 'https://e.com/a".png', 'https://e.com/a b.png', 'https://e.com/a(1).png', ''] as $bad) {
            $this->assertNull(Covers::safe($bad), $bad);
        }

        $this->assertSame('https://e.com/a.png', Covers::safe(' https://e.com/a.png '));
        $this->assertSame('/assets/files/a.png', Covers::safe('/assets/files/a.png'));
    }

    #[Test]
    public function ratios_are_clamped_and_unknown_is_null()
    {
        $this->assertNull(Covers::ratio(0, 100));
        $this->assertSame(Covers::MIN_RATIO, Covers::ratio(10000, 100));
        $this->assertSame(Covers::MAX_RATIO, Covers::ratio(100, 10000));
        $this->assertSame(750, Covers::ratio(1600, 1200));
    }

    #[Test]
    public function the_measuring_job_refuses_private_addresses()
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '169.254.169.254', '::1', '[::1]', 'localhost', '0.0.0.0'] as $host) {
            $this->assertNull(MeasureCover::publicAddress($host), $host);
        }

        $this->assertSame('93.184.216.34', MeasureCover::publicAddress('93.184.216.34'));
    }
}
