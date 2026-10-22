(function () {
  'use strict';

  var presets = {
    cloudflare: { region: 'auto', endpoint: 'https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com', path_style: true, help: 'Replace YOUR_ACCOUNT_ID. Region stays auto.' },
    amazon_s3: { region: 'us-east-1', endpoint: 'https://s3.us-east-1.amazonaws.com', path_style: false, help: 'Match region to your bucket.' },
    wasabi: { region: 'us-east-1', endpoint: 'https://s3.us-east-1.wasabisys.com', path_style: true, help: 'Use your Wasabi region code.' },
    backblaze_b2: { region: 'us-west-004', endpoint: 'https://s3.us-west-004.backblazeb2.com', path_style: true, help: 'Use the S3 endpoint from B2 bucket settings.' },
    digitalocean_spaces: { region: 'nyc3', endpoint: 'https://nyc3.digitaloceanspaces.com', path_style: false, help: 'Region is the datacenter slug (nyc3, ams3, …).' },
    custom: { region: 'us-east-1', endpoint: '', path_style: true, help: 'MinIO, Linode, Contabo, Scaleway, or any S3 API.' }
  };

  var providerEl = document.getElementById('storage_provider');
  var regionEl = document.getElementById('storage_region');
  var endpointEl = document.getElementById('storage_endpoint');
  var pathStyleEl = document.getElementById('storage_path_style');
  var helpEl = document.getElementById('provider_help');
  if (!providerEl) return;

  function suggestEndpoint(provider, region) {
    region = (region || '').toLowerCase().trim();
    if (!region) return '';
    if (provider === 'amazon_s3') {
      return region === 'us-east-1' ? 'https://s3.amazonaws.com' : 'https://s3.' + region + '.amazonaws.com';
    }
    if (provider === 'wasabi') return 'https://s3.' + region + '.wasabisys.com';
    if (provider === 'backblaze_b2') return 'https://s3.' + region + '.backblazeb2.com';
    if (provider === 'digitalocean_spaces') return 'https://' + region + '.digitaloceanspaces.com';
    return '';
  }

  function apply(fillEndpoint) {
    var preset = presets[providerEl.value];
    if (!preset) return;
    if (helpEl) helpEl.textContent = preset.help || '';
    if (regionEl && fillEndpoint) regionEl.value = preset.region;
    if (pathStyleEl) pathStyleEl.checked = !!preset.path_style;
    if (fillEndpoint && endpointEl) {
      endpointEl.value = suggestEndpoint(providerEl.value, regionEl ? regionEl.value : preset.region) || preset.endpoint;
    }
  }

  providerEl.addEventListener('change', function () { apply(true); });
  if (regionEl) {
    regionEl.addEventListener('change', function () {
      var id = providerEl.value;
      if (id === 'cloudflare' || id === 'custom') return;
      var suggested = suggestEndpoint(id, regionEl.value);
      if (suggested && endpointEl) endpointEl.value = suggested;
    });
  }
})();
