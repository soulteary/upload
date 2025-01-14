<?php

/*
 * This file is part of flagrow/upload.
 *
 * Copyright (c) Flagrow.
 *
 * http://flagrow.github.io
 *
 * For the full copyright and license information, please view the license.md
 * file that was distributed with this source code.
 */

namespace Flagrow\Upload\Providers;

use OSS\OssClient;
use Aws\S3\S3Client;
use Flagrow\Upload\Adapters;
use Flagrow\Upload\Helpers\Settings;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Adapter as FlyAdapters;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Aliyun\Flysystem\AliyunOss\AliyunOssAdapter;
use Techyah\Flysystem\OVH\OVHAdapter;
use Techyah\Flysystem\OVH\OVHClient;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        /** @var Settings $settings */
        $settings = $this->app->make(Settings::class);

        $this->instantiateUploadAdapters($this->app);

        if ($settings->get('overrideAvatarUpload')) {
            // .. todo
        }
    }

    /**
     * Sets the upload adapter for the specific preferred service.
     *
     * @param Container $app
     */
    protected function instantiateUploadAdapters(Container $app)
    {
        /** @var Settings $settings */
        $settings = $app->make(Settings::class);

        $settings->getMimeTypesConfiguration()
            ->each(function ($mimetype) use ($app, $settings) {
                $adapter = Arr::get($mimetype, 'adapter', $mimetype);

                // Skip if already bound.
                if ($app->bound("flagrow.upload-adapter.$adapter")) {
                    return;
                }

                $app->bind("flagrow.upload-adapter.$adapter", function () use ($settings, $adapter) {
                    switch ($adapter) {
                        case 'aliyun':
                            if (class_exists(OssClient::class)) {
                                return $this->aliyun($settings);
                            }
                        case 'aws-s3':
                            if (class_exists(S3Client::class)) {
                                return $this->awsS3($settings);
                            }
                        case 'ovh-svfs':
                            if (class_exists(OVHClient::class)) {
                                return $this->ovh($settings);
                            }
                        case 'imgur':
                            return $this->imgur($settings);

                        default:
                            return $this->local($settings);
                    }
                });
            });
    }

    /**
     * @param Settings $settings
     *
     * @return Adapters\AliyunOSS
     */
    protected function aliyun(Settings $settings)
    {
        return new Adapters\AliyunOss(
            new AliyunOssAdapter(
                new OssClient($settings->get('aliyunAppid'),$settings->get('aliyunKey'),$settings->get('aliyunEndPoint')),
                $settings->get('aliyunBucket')
            )
        );
    }

    /**
     * @param Settings $settings
     *
     * @return Adapters\AwsS3
     */
    protected function awsS3(Settings $settings)
    {
        return new Adapters\AwsS3(
            new AwsS3Adapter(
                new S3Client([
                    'credentials' => [
                        'key'    => $settings->get('awsS3Key'),
                        'secret' => $settings->get('awsS3Secret'),
                    ],
                    'region'  => empty($settings->get('awsS3Region')) ? null : $settings->get('awsS3Region'),
                    'version' => 'latest',
                ]),
                $settings->get('awsS3Bucket')
            )
        );
    }

    /**
     * @param Settings $settings
     *
     * @return Adapters\OVH
     */
    protected function ovh(Settings $settings)
    {
        $client = new OVHClient([
            'username'  => $settings->get('ovhUsername'),
            'password'  => $settings->get('ovhPassword'),
            'tenantId'  => $settings->get('ovhTenantId'),
            'container' => $settings->get('ovhContainer'),
            'region'    => empty($settings->get('ovhRegion')) ? 'BHS1' : $settings->get('ovhRegion'),
        ]);

        return new Adapters\OVH(
            new OVHAdapter($client->getContainer())
        );
    }

    /**
     * @param Settings $settings
     *
     * @return Adapters\Imgur
     */
    protected function imgur(Settings $settings)
    {
        return new Adapters\Imgur(
            new Guzzle([
                'base_uri' => 'https://api.imgur.com/3/',
                'headers'  => [
                    'Authorization' => 'Client-ID '.$settings->get('imgurClientId'),
                ],
            ])
        );
    }

    /**
     * @param Settings $settings
     *
     * @return Adapters\Local
     */
    protected function local(Settings $settings)
    {
        return new Adapters\Local(
            new FlyAdapters\Local(public_path('assets/files'))
        );
    }
}
