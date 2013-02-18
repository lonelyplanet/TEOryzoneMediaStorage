<?php

/*
 * This file is part of the Oryzone/MediaStorage package.
 *
 * (c) Javier F. Escribano <javier@touristeye.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Oryzone\MediaStorage\Provider;

use Oryzone\MediaStorage\Provider\Provider,
    Oryzone\MediaStorage\Model\MediaInterface,
    Oryzone\MediaStorage\Context\ContextInterface,
    Oryzone\MediaStorage\Variant\VariantInterface,
    Oryzone\MediaStorage\Exception\InvalidArgumentException;

class LinkProvider extends Provider
{
    /**
     * Default content type (file).
     * Can be redefined in subclasses without the need to redefine the getContentType method
     *
     * @var int
     */
    protected static $contentType = self::CONTENT_TYPE_STRING;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'link';
    }

    /**
     * {@inheritDoc}
     */
    public function hasChangedContent(MediaInterface $media)
    {
        $content = $media->getContent();

        return ($content != NULL && $media->getMetaValue('id') !== md5_file($content));
    }

    /**
     * {@inheritDoc}
     */
    public function validateContent($content)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(MediaInterface $media, ContextInterface $context)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function process(MediaInterface $media, VariantInterface $variant, \SplFileInfo $source = NULL)
    {
        return $source;
    }

    /**
     * {@inheritDoc}
     */
    public function render(MediaInterface $media, VariantInterface $variant, $url = NULL, $options = array())
    {
        $attributes = array(
            'title' => $media->getName()
        );
        if(isset($options['attributes']))
            $attributes = array_merge($attributes, $options['attributes']);

        $htmlAttributes = '';
            foreach($attributes as $key => $value)
                if($value !== NULL)
                    $htmlAttributes .= $key . '="' . $value . '" ';

        return sprintf('<a href="%s" %s>%s</a>',
            $url, $htmlAttributes, $media->getName());
    }
}
