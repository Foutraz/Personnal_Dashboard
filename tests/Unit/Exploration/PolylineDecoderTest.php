<?php

namespace Tests\Unit\Exploration;

use Functional\Exploration\Support\PolylineDecoder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolylineDecoderTest extends TestCase
{
    private PolylineDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new PolylineDecoder;
    }

    #[Test]
    public function it_decodes_the_canonical_google_fixture(): void
    {
        $points = $this->decoder->decode('_p~iF~ps|U_ulLnnqC_mqNvxq`@');

        $this->assertCount(3, $points);

        $this->assertEqualsWithDelta(38.5, $points[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(-120.2, $points[0]['lng'], 0.0001);

        $this->assertEqualsWithDelta(40.7, $points[1]['lat'], 0.0001);
        $this->assertEqualsWithDelta(-120.95, $points[1]['lng'], 0.0001);

        $this->assertEqualsWithDelta(43.252, $points[2]['lat'], 0.0001);
        $this->assertEqualsWithDelta(-126.453, $points[2]['lng'], 0.0001);
    }

    #[Test]
    public function it_decodes_a_single_point(): void
    {
        $points = $this->decoder->decode('_ibE_seK');

        $this->assertCount(1, $points);
        $this->assertEqualsWithDelta(1.0, $points[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $points[0]['lng'], 0.0001);
    }

    #[Test]
    public function it_returns_an_empty_array_for_an_empty_polyline(): void
    {
        $this->assertSame([], $this->decoder->decode(''));
    }
}
