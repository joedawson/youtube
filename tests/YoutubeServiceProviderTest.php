<?php

namespace Dawson\Youtube\Tests;

class YoutubeServiceProviderTest extends TestCase
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties 
     | ------------------------------------------------------------------------------------------------
     */
    /** @var  \Dawson\Youtube\YoutubeServiceProvider */
    private $provider;

    /* ------------------------------------------------------------------------------------------------
     |  Main Functions 
     | ------------------------------------------------------------------------------------------------
     */
    public function setUp()
    {
        parent::setUp();

        $this->provider = $this->app->getProvider(\Dawson\Youtube\YoutubeServiceProvider::class);
    }

    public function tearDown()
    {
        unset($this->provider);

        parent::tearDown();
    }

    /* ------------------------------------------------------------------------------------------------
     |  Test Functions 
     | ------------------------------------------------------------------------------------------------
     */
    /** @test */
    public function it_can_be_instantiated()
    {
        $expectations = [
            \Illuminate\Support\ServiceProvider::class,
            \Dawson\Youtube\YoutubeServiceProvider::class,
        ];

        foreach ($expectations as $expected) {
            $this->assertInstanceOf($expected, $this->provider);
        }
    }

    /** @test */
    public function it_can_provides()
    {
        $expected = [
            \Dawson\Youtube\Contracts\Youtube::class,
            'youtube',
        ];

        $this->assertSame($expected, $this->provider->provides());
    }
}
