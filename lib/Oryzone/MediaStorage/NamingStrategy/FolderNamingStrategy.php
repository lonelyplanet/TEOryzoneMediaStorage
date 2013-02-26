<?php

/*
 * This file is part of the Oryzone/MediaStorage package.
 *
 * (c) Javier F. Escribano <javier@touristeye.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Oryzone\MediaStorage\NamingStrategy;

use Gaufrette\Filesystem;

use Oryzone\MediaStorage\Model\MediaInterface,
    Oryzone\MediaStorage\Variant\VariantInterface,
    Oryzone\MediaStorage\Exception\InvalidArgumentException;

class FolderNamingStrategy extends NamingStrategy
{

    /**
     * {@inheritDoc}
     */
    public function generateName(MediaInterface $media, VariantInterface $variant, Filesystem $filesystem)
    {
        if ( !$media->getFilename() ) {
            $media->setFilename(uniqid().'.jpg');
        }

        return $media->getContext().'/'.$variant->getName().'/' . substr($media->getFilename(), 0, -4);
    }
}
