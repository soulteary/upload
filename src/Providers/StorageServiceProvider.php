<?php
/*
 * This file is part of flagrow/flarum-ext-file-upload.
 *
 * Copyright (c) Flagrow.
 *
 * http://flagrow.github.io
 *
 * For the full copyright and license information, please view the license.md
 * file that was distributed with this source code.
 */

namespace Flagrow\Upload\Providers;

use Flagrow\Upload\Adapters;
use Flagrow\Upload\Commands\UploadHandler;
use Flagrow\Upload\Contracts\UploadAdapter;
use Flagrow\Upload\Helpers\Settings;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Adapter as FlyAdapters;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;

class StorageServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $uploadAdapter = function (Container $app) {
            return $this->instantiateUploadAdapter($app);
        };

        $this->app->when(UploadHandler::class)
            ->needs(UploadAdapter::class)
            ->give($uploadAdapter);
    }

    /**
     * Sets the upload adapter for the specific preferred service.
     *
     * @param $app
     * @return FilesystemInterface
     */
    protected function instantiateUploadAdapter($app)
    {
        /** @var Settings $settings */
        $settings = $app->make(Settings::class);

        switch ($settings->get('uploadMethod', 'local')) {
            default:
                return $this->local($settings);
        }
    }

    protected function local(Settings $settings)
    {
        return new Adapters\Local(
            new Filesystem(
                new FlyAdapters\Local(public_path('assets/files')),
                $settings->get('local', [])
            )
        );
    }
}