<?php

/*
 * This file is part of the Oryzone/MediaStorage package.
 *
 * (c) Luciano Mammino <lmammino@oryzone.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Oryzone\MediaStorage;

use Gaufrette\StreamMode,
    Gaufrette\Stream\Local;

use Oryzone\MediaStorage\Event\Adapter\EventDispatcherAdapterInterface,
    Oryzone\MediaStorage\Persistence\Adapter\PersistenceAdapterInterface,
    Oryzone\MediaStorage\Cdn\CdnFactoryInterface,
    Oryzone\MediaStorage\Context\ContextFactoryInterface,
    Oryzone\MediaStorage\Filesystem\FilesystemFactoryInterface,
    Oryzone\MediaStorage\NamingStrategy\NamingStrategyFactoryInterface,
    Oryzone\MediaStorage\Provider\ProviderFactoryInterface,
    Oryzone\MediaStorage\Model\MediaInterface,
    Oryzone\MediaStorage\Variant\VariantInterface,
    Oryzone\MediaStorage\Provider\ProviderInterface,
    Oryzone\MediaStorage\Variant\VariantNode,
    Oryzone\MediaStorage\Exception\InvalidArgumentException,
    Oryzone\MediaStorage\Exception\InvalidContentException,
    Oryzone\MediaStorage\Exception\IOException,
    Oryzone\MediaStorage\Exception\VariantProcessingException;

class MediaStorage implements MediaStorageInterface
{
    /**
     * @var Event\Adapter\EventDispatcherAdapterInterface $eventAdapter
     */
    protected $eventDispatcherAdapter;

    /**
     * @var Persistence\Adapter\PersistenceAdapterInterface $persistenceAdapter
     */
    protected $persistenceAdapter;

    /**
     * @var Cdn\CdnFactoryInterface $cdnFactory
     */
    protected $cdnFactory;

    /**
     * @var Context\ContextFactoryInterface $contextFactory
     */
    protected $contextFactory;

    /**
     * @var Filesystem\FilesystemFactoryInterface $filesystemFactory
     */
    protected $filesystemFactory;

    /**
     * @var Provider\ProviderFactoryInterface $providerFactory
     */
    protected $providerFactory;

    /**
     * @var NamingStrategy\NamingStrategyFactoryInterface $namingStrategyFactory
     */
    protected $namingStrategyFactory;

    /**
     * @var null|string $defaultCdn
     */
    protected $defaultCdn;

    /**
     * @var null|string $defaultContext
     */
    protected $defaultContext;

    /**
     * @var null|string $defaultFilesystem
     */
    protected $defaultFilesystem;

    /**
     * @var null|string $defaultProvider
     */
    protected $defaultProvider;

    /**
     * @var null|string $defaultNamingStrategy
     */
    protected $defaultNamingStrategy;

    /**
     * @var null|string $defaultVariant
     */
    protected $defaultVariant;

    /**
     * Constructor
     *
     * @param Event\Adapter\EventDispatcherAdapterInterface   $eventDispatcherAdapter
     * @param Persistence\Adapter\PersistenceAdapterInterface $persistenceAdapter
     * @param Cdn\CdnFactoryInterface                         $cdnFactory
     * @param Context\ContextFactoryInterface                 $contextFactory
     * @param Filesystem\FilesystemFactoryInterface           $filesystemMap
     * @param Provider\ProviderFactoryInterface               $providerFactory
     * @param NamingStrategy\NamingStrategyFactoryInterface   $namingStrategyFactory
     * @param string|null                                     $defaultCdn
     * @param string|null                                     $defaultContext
     * @param string|null                                     $defaultFilesystem
     * @param string|null                                     $defaultProvider
     * @param string|null                                     $defaultNamingStrategy
     * @param string|null                                     $defaultVariant
     */
    public function __construct(EventDispatcherAdapterInterface $eventDispatcherAdapter,
                                PersistenceAdapterInterface $persistenceAdapter, CdnFactoryInterface $cdnFactory,
                                ContextFactoryInterface $contextFactory, FilesystemFactoryInterface $filesystemMap,
                                ProviderFactoryInterface $providerFactory,
                                NamingStrategyFactoryInterface $namingStrategyFactory, $defaultCdn = NULL,
                                $defaultContext = NULL, $defaultFilesystem = NULL, $defaultProvider = NULL,
                                $defaultNamingStrategy = NULL, $defaultVariant = NULL)
    {
        $this->eventDispatcherAdapter = $eventDispatcherAdapter;
        $this->persistenceAdapter = $persistenceAdapter;
        $this->cdnFactory = $cdnFactory;
        $this->contextFactory = $contextFactory;
        $this->filesystemFactory = $filesystemMap;
        $this->providerFactory = $providerFactory;
        $this->namingStrategyFactory = $namingStrategyFactory;
        $this->defaultCdn = $defaultCdn;
        $this->defaultContext = $defaultContext;
        $this->defaultFilesystem = $defaultFilesystem;
        $this->defaultProvider = $defaultProvider;
        $this->defaultNamingStrategy = $defaultNamingStrategy;
        $this->defaultVariant = $defaultVariant;
    }

    /**
     * Creates an instance of \SplFileInfo instance from a source.
     * Source may be a string (of a path) an instance of SPL <code>File</code>
     *
     * @param  \Oryzone\MediaStorage\Model\MediaInterface     $media
     * @param  \Oryzone\MediaStorage\Variant\VariantInterface $variant
     * @throws Exception\IOException
     * @return \SplFileInfo
     */
    protected function createFileInstance(MediaInterface $media, VariantInterface $variant)
    {
        $source = $media->getContent();

        if (is_string($source)) {
            if(!is_file($source))
                throw new IOException(
                    sprintf('Cannot load file "%s" for media "%s", variant "%s". File not found.', $source, $media, $variant->getName()), $source);

            return new \SplFileInfo($source);
        } elseif(is_object($source) && $source instanceof \SplFileInfo)

            return $source;

        throw new IOException(
            sprintf('Object of class "%s" is not an instance of \SplFileInfo so it cannot be loaded as a file while processing media "%s", variant "%s"', get_class($source), $media, $variant->getName()), $source);
    }

    /**
     * @param \SplFileInfo             $file
     * @param string                   $filename
     * @param \Gaufrette\Filesystem    $filesystem
     * @param Variant\VariantInterface $variant
     *
     * @return string
     */
    protected function saveFileToFilesystem(\SplFileInfo $file, $filename, \Gaufrette\Filesystem $filesystem, VariantInterface $variant)
    {
        $extension = $file->getExtension();
        $filename .= '.'.$extension;

        $src = new Local($file->getPathname());

        //$dst = $filesystem->getAdapter()->createFileStream($filename, $filesystem);
        $dst = $filesystem->createStream($filename);

        $src->open(new StreamMode('rb+'));
        $dst->open(new StreamMode('ab+'));

        while (!$src->eof()) {
            $data    = $src->read(100000);
            $written = $dst->write($data);
        }
        $dst->close();
        $src->close();

        $variant->setFilename($filename);
        $variant->setStatus(VariantInterface::STATUS_READY);

        return $filename;
    }

    /**
     * {@inheritDoc}
     */
    public function getCdn($name = NULL)
    {
        if (!$name) {
            if(!$this->defaultCdn)
                throw new InvalidArgumentException('Trying to load the default CDN but a it has not been set');
            $name = $this->defaultCdn;
        }

        return $this->cdnFactory->get($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getContext($name = NULL)
    {
        if (!$name) {
            if(!$this->defaultContext)
                throw new InvalidArgumentException('Trying to load the default Context but it has not been set');

            $name = $this->defaultContext;
        }

        return $this->contextFactory->get($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getFilesystem($name = NULL)
    {
        if (!$name) {
            if(!$this->defaultFilesystem)
                throw new InvalidArgumentException('Trying to load the default Filesystem but it has not been set');

            $name = $this->defaultFilesystem;
        }

        return $this->filesystemFactory->get($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getProvider($name = NULL, $options = array())
    {
        if (!$name) {
            if(!$this->defaultProvider)
                throw new InvalidArgumentException('Trying to load the default Provider but it has not been set');

            $name = $this->defaultProvider;
        }

        return $this->providerFactory->get($name, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getNamingStrategy($name = NULL)
    {
        if (!$name) {
            if(!$this->defaultNamingStrategy)
                throw new InvalidArgumentException('Trying to load the default Naming strategy but it has not been set');

            $name = $this->defaultNamingStrategy;
        }

        return $this->namingStrategyFactory->get($name);
    }

    /**
     * Processes a given media
     *
     * @param Model\Media $media
     * @param bool        $isUpdate
     *
     * @throws Exception\VariantProcessingException
     * @return bool
     */
    protected function processMedia(MediaInterface $media, $isUpdate = FALSE)
    {
        $this->eventDispatcherAdapter->onBeforeProcess($media);

        $context = $this->getContext($media->getContext());
        $provider = $this->getProvider($context->getProviderName(), $context->getProviderOptions());
        $variantsTree = $context->buildVariantTree();
        $filesystem = $this->getFilesystem($context->getFilesystemName());
        $namingStrategy = $this->getNamingStrategy($context->getNamingStrategyName());

        $generatedFiles = array();

        $variantsTree->visit(
            function(VariantNode $node, $level)
            use ($provider, $context, $media, $filesystem, $namingStrategy, &$generatedFiles, $isUpdate)
            {
                $variant = $node->getContent();
                $parent = $node->getParent() ? $node->getParent()->getContent() : NULL;
                if ($isUpdate && $media->hasVariant($variant->getName())) {
                    $existingVariant = $media->getVariantInstance($variant->getName());
                    if($existingVariant->isReady())
                        $filesystem->delete($existingVariant->getFilename());
                    $media->removeVariant($variant->getName());
                }
                $media->addVariant($variant);

                $file = NULL;
                if ($provider->getContentType() == ProviderInterface::CONTENT_TYPE_FILE) {
                    if ($parent) {
                        // checks if the parent file has been generated in a previous step
                        if(isset($generatedFiles[$parent->getName()]))
                            $file = $generatedFiles[$parent->getName()];
                        else {
                            //otherwise try to read the file from the storage if the variant is ready
                            //TODO

                            throw new VariantProcessingException(
                                sprintf('Cannot load parent variant ("%s") file for variant "%s" of media "%s"', $parent->getName(), $variant->getName(), $media),
                                $media, $variant);
                        }

                    } else
                        $file = $this->createFileInstance($media, $variant);
                }

                switch ($variant->getMode()) {
                    case VariantInterface::MODE_INSTANT:
                        $result = $provider->process($media, $variant, $file);
                        if ($result) {
                            $generatedFiles[$variant->getName()] = $result;
                            $name = $namingStrategy->generateName($media, $variant, $filesystem);
                            $this->saveFileToFilesystem($result, $name, $filesystem, $variant);
                        }
                        break;

                    case VariantInterface::MODE_LAZY:
                        // TODO
                        break;

                    case VariantInterface::MODE_QUEUE:
                        // TODO
                        break;
                }

                //updates the variant in the media (to store the new values)
                $media->addVariant($variant);
            }
        );

        $provider->removeTempFiles();

        $this->eventDispatcherAdapter->onAfterProcess($media);

        return TRUE; // marks the media as updated
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareMedia(MediaInterface $media, $isUpdate = false)
    {
        $context = $this->getContext($media->getContext());
        $provider = $this->getProvider($context->getProviderName(), $context->getProviderOptions());

        if (!$isUpdate || $isUpdate && $provider->hasChangedContent($media)) {
            if(!$media->getContext())
                $media->setContext($context->getName());

            if( !$provider->validateContent($media->getContent()) )
                throw new InvalidContentException(sprintf('Invalid content of type "%s" for media "%s" detected by "%s" provider',
                        gettype($media->getContent())=='object'?get_class($media->getContent()):gettype($media->getContent()).'('.$media->getContent().')', $media, $provider->getName()),
                    $provider, $media);

            $provider->prepare($media, $context);

            return TRUE;
        }

        return FALSE;
    }

    /**
     * {@inheritDoc}
     */
    protected function storeMedia(MediaInterface $media)
    {
        $this->eventDispatcherAdapter->onBeforeStore($media);
        $this->processMedia($media);
        $this->eventDispatcherAdapter->onAfterStore($media);

        $this->eventDispatcherAdapter->onBeforeModelPersist($media);
        $this->persistenceAdapter->save($media);
        $this->eventDispatcherAdapter->onAfterModelPersist($media);
    }

    protected function updateMedia(MediaInterface $media)
    {
        $this->eventDispatcherAdapter->onBeforeUpdate($media);
        $this->processMedia($media, TRUE);
        $this->eventDispatcherAdapter->onAfterUpdate($media);

        $this->eventDispatcherAdapter->onBeforeModelPersist($media, TRUE);
        $this->persistenceAdapter->update($media);
        $this->eventDispatcherAdapter->onAfterModelPersist($media, TRUE);
    }

    /**
     * {@inheritDoc}
     */
    public function store(MediaInterface $media)
    {
        $this->prepareMedia($media);
        $this->storeMedia($media);
    }

    /**
     * {@inheritDoc}
     */
    public function update(MediaInterface $media)
    {
        if ( $this->prepareMedia($media, TRUE) )
            $this->updateMedia($media);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(MediaInterface $media)
    {
        //TODO make removal of physical files asynchronous (optionally)
        $this->eventDispatcherAdapter->onBeforeRemove($media);

        $context = $this->getContext($media->getContext());
        $filesystem = $this->getFilesystem($context->getFilesystemName());

        foreach ($media->getVariants() as $name => $value) {
            $variant = $media->getVariantInstance($name);
            if($variant->isReady() && $filesystem->has($variant->getFilename()))
                $filesystem->delete($variant->getFilename());
        }

        $this->eventDispatcherAdapter->onAfterRemove($media);

        $this->eventDispatcherAdapter->onBeforeModelRemove($media);
        $this->persistenceAdapter->remove($media);
        $this->eventDispatcherAdapter->onAfterModelRemove($media);
    }

    /**
     * {@inheritDoc}
     */
    public function getUrl(MediaInterface $media, $variant = NULL, $options = array())
    {
        $context = $this->getContext($media->getContext());
        $cdn = $this->getCdn($context->getCdnName());
        $variant = $media->getVariantInstance($variant);

        return $cdn->getUrl($media, $variant, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function render(MediaInterface $media, $variant = NULL, $options = array())
    {
        $context = $this->getContext($media->getContext());

        if ($variant === NULL) {
            if($context->getDefaultVariant() !== NULL)
                $variant = $context->getDefaultVariant();
            else
                $variant = $this->defaultVariant;
        }

        $provider = $this->getProvider($context->getProviderName(), $context->getProviderOptions());
        $variantInstance = $media->getVariantInstance($variant);

        $urlOptions = array();
        if(isset($options['_url']))
            $urlOptions = $options['_url'];

        return $provider->render($media, $variantInstance, $this->getUrl($media, $variant, $urlOptions), $options);
    }

}