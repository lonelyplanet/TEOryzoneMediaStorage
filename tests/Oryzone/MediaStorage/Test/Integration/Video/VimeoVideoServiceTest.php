<?php
namespace Oryzone\MediaStorage\Test\Integration\Video;

/*
 * This file is part of the Oryzone/MediaStorage package.
 *
 * (c) Luciano Mammino <lmammino@oryzone.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Oryzone\MediaStorage\Integration\Video\VimeoVideoService;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2013-01-06 at 11:49:36.
 */
class VimeoVideoServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var VimeoVideoService
     */
    protected $vimeo;

    // from http://vimeo.com/api/v2/video/17419531.xml
    protected static $RESPONSE = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<videos>
    <video>
        <id>17419531</id>
        <title>People of the Coral Triangle // James Morgan // 1080p</title>
        <description>www.jamesmorganphotography.co.uk&lt;br /&gt;  www.facebook.com/jamesmorganfoto&lt;br /&gt;  www.twitter.com/jamesmorganfoto&lt;br /&gt;  &lt;br /&gt;  Featuring music by Boards of Canada and Pantha Du Prince</description>
        <url>http://vimeo.com/17419531</url>
        <upload_date>2010-12-02 17:39:22</upload_date>
        <mobile_url>http://vimeo.com/m/17419531</mobile_url>
        <thumbnail_small>http://b.vimeocdn.com/ts/108/901/108901672_100.jpg</thumbnail_small>
        <thumbnail_medium>http://b.vimeocdn.com/ts/108/901/108901672_200.jpg</thumbnail_medium>
        <thumbnail_large>http://b.vimeocdn.com/ts/108/901/108901672_640.jpg</thumbnail_large>
        <user_id>2835775</user_id>
        <user_name>James Morgan Photography</user_name>
        <user_url>http://vimeo.com/jamesmorganphoto</user_url>
        <user_portrait_small>http://b.vimeocdn.com/ps/362/575/3625759_30.jpg</user_portrait_small>
        <user_portrait_medium>http://b.vimeocdn.com/ps/362/575/3625759_75.jpg</user_portrait_medium>
        <user_portrait_large>http://b.vimeocdn.com/ps/362/575/3625759_100.jpg</user_portrait_large>
        <user_portrait_huge>http://b.vimeocdn.com/ps/362/575/3625759_300.jpg</user_portrait_huge>
        <stats_number_of_likes>1182</stats_number_of_likes>
        <stats_number_of_plays>61091</stats_number_of_plays>
        <stats_number_of_comments>56</stats_number_of_comments>
        <duration>697</duration>
        <width>1280</width>
        <height>720</height>
        <tags>coral triangle, the coral triangle, james morgan, james morgan photography</tags>
        <embed_privacy>anywhere</embed_privacy>
    </video>
</videos>
EOF;

    protected static $PARSED_METADATA = array (
        'id' => '17419531',
        'title' => 'People of the Coral Triangle // James Morgan // 1080p',
        'description' => 'www.jamesmorganphotography.co.uk  www.facebook.com/jamesmorganfoto  www.twitter.com/jamesmorganfoto    Featuring music by Boards of Canada and Pantha Du Prince',
        'url' => 'http://vimeo.com/17419531',
        'upload_date' => '2010-12-02 17:39:22',
        'mobile_url' => 'http://vimeo.com/m/17419531',
        'thumbnail_small' => 'http://b.vimeocdn.com/ts/108/901/108901672_100.jpg',
        'thumbnail_medium' => 'http://b.vimeocdn.com/ts/108/901/108901672_200.jpg',
        'thumbnail_large' => 'http://b.vimeocdn.com/ts/108/901/108901672_640.jpg',
        'user_id' => '2835775',
        'user_name' => 'James Morgan Photography',
        'user_url' => 'http://vimeo.com/jamesmorganphoto',
        'user_portrait_small' => 'http://b.vimeocdn.com/ps/362/575/3625759_30.jpg',
        'user_portrait_medium' => 'http://b.vimeocdn.com/ps/362/575/3625759_75.jpg',
        'user_portrait_large' => 'http://b.vimeocdn.com/ps/362/575/3625759_100.jpg',
        'user_portrait_huge' => 'http://b.vimeocdn.com/ps/362/575/3625759_300.jpg',
        'stats_number_of_likes' => '1182',
        'stats_number_of_plays' => '61091',
        'stats_number_of_comments' => '56',
        'duration' => '697',
        'width' => '1280',
        'height' => '720',
        'tags' =>
        array (
            0 => 'coral triangle',
            1 => 'the coral triangle',
            2 => 'james morgan',
            3 => 'james morgan photography',
        ),
        'embed_privacy' => 'anywhere',
    );

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $buzz = $this->getMock('\Buzz\Browser');
        $buzzResponse = $this->getMock('\Buzz\Message\Response');
        $cache = $this->getMock('\Doctrine\Common\Cache\Cache');

        $buzzResponse->expects($this->any())
                     ->method('getContent')
                     ->will($this->returnValue(self::$RESPONSE));

        $buzz->expects($this->any())
             ->method('get')
             ->will($this->returnValue($buzzResponse));

        $this->vimeo = new VimeoVideoService($buzz, $cache);
        $this->vimeo->load('something');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testLoad()
    {
        $this->assertEquals(self::$PARSED_METADATA, $this->vimeo->getMetadata());
    }

    public function testGetMetaValue()
    {
        $this->assertEquals('default', $this->vimeo->getMetaValue('unknown', 'default'));
    }
}