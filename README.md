## Mailing List Stats Tool

This tool allows you to see some stats for a mailing list.

### How to run

1. Clone the repository
2. Run `composer install`
3. Run `php collector.php` to download the mailing list archive/history. It is a bit of a hack, so I recommend using the
   online version or adjusting the max range/window in `collector.php` to avoid downloading the entire history.
4. Run `cd public; php -S localhost:8000` to start the built-in PHP server OR `docker compose up`.

### Environment variables:

- `SALT`: A salt to hash the email addresses. This is used to anonymize the email addresses. If you don’t set this, the
  email address will be a simple MD5 hash.
- `UNSAFE_EMAILS`: If set, the email addresses will not be anonymized. This is useful for debugging.

### Production build

1. Push to `main`
2. Wait for GitHub Actions to build the production version
3. Update `k8s-manifest.yaml` with the current commit hash
4. Apply the manifest to the Kubernetes cluster

### License

This tool is licensed to Robert Landers, all rights reserved.
You ARE NOT allowed to use it for any purpose beyond your own research and personal use.
