<?php
declare(strict_types=1);

final class StorageProviders
{
    /** @return array<string,array{label:string,region:string,endpoint:string,path_style:bool,help:string}> */
    public static function all(): array
    {
        return [
            'cloudflare' => [
                'label' => 'Cloudflare R2',
                'region' => 'auto',
                'endpoint' => 'https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com',
                'path_style' => true,
                'help' => 'Replace YOUR_ACCOUNT_ID. Region stays auto.',
            ],
            'amazon_s3' => [
                'label' => 'Amazon S3',
                'region' => 'us-east-1',
                'endpoint' => 'https://s3.us-east-1.amazonaws.com',
                'path_style' => false,
                'help' => 'Match region to your bucket.',
            ],
            'wasabi' => [
                'label' => 'Wasabi',
                'region' => 'us-east-1',
                'endpoint' => 'https://s3.us-east-1.wasabisys.com',
                'path_style' => true,
                'help' => 'Use your Wasabi region code.',
            ],
            'backblaze_b2' => [
                'label' => 'Backblaze B2',
                'region' => 'us-west-004',
                'endpoint' => 'https://s3.us-west-004.backblazeb2.com',
                'path_style' => true,
                'help' => 'Use the S3 endpoint from B2 bucket settings.',
            ],
            'digitalocean_spaces' => [
                'label' => 'DigitalOcean Spaces',
                'region' => 'nyc3',
                'endpoint' => 'https://nyc3.digitaloceanspaces.com',
                'path_style' => false,
                'help' => 'Region is the datacenter slug (nyc3, ams3, …).',
            ],
            'custom' => [
                'label' => 'Other (S3-compatible)',
                'region' => 'us-east-1',
                'endpoint' => '',
                'path_style' => true,
                'help' => 'MinIO, Linode, Contabo, Scaleway, or any S3 API.',
            ],
        ];
    }

    public static function sanitize(string $id): string
    {
        if ($id === 'cloudflare_r2') {
            $id = 'cloudflare';
        }
        return isset(self::all()[$id]) ? $id : 'cloudflare';
    }

    public static function suggestEndpoint(string $provider, string $region): string
    {
        $region = strtolower(trim($region));
        return match ($provider) {
            'amazon_s3' => $region === 'us-east-1'
                ? 'https://s3.amazonaws.com'
                : 'https://s3.' . $region . '.amazonaws.com',
            'wasabi' => 'https://s3.' . $region . '.wasabisys.com',
            'backblaze_b2' => 'https://s3.' . $region . '.backblazeb2.com',
            'digitalocean_spaces' => 'https://' . $region . '.digitaloceanspaces.com',
            default => '',
        };
    }
}
