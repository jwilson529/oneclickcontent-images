# Clean Docker Repro Playbook

This is a reusable workflow for reproducing WordPress plugin UI and admin issues on a clean Docker WordPress install.

The goal is to test the plugin as an installed zip, not just as a mounted working tree. That catches packaging problems, fresh-install defaults, asset loading issues, and editor/runtime problems that do not show up in an existing local site.

## When To Use This

Use this when:

- The plugin behaves differently on a fresh site than on your main local install.
- A UI control is missing, duplicated, or misplaced.
- A packaged zip installs, but the live admin/editor behavior is wrong.
- Gutenberg behavior is suspect, especially with iframe-based editing.
- You need screenshots, rendered DOM, or container logs from a clean install.

## Inputs

Set these once per repro:

```bash
PROJECT=plugin-debug
HTTP_PORT=8089
DB_PORT=33069
SITE_URL="http://127.0.0.1:${HTTP_PORT}"
ZIP_PATH="dist/your-plugin-slug.zip"
PLUGIN_SLUG="your-plugin-slug"
PLUGIN_BASENAME="${PLUGIN_SLUG}/${PLUGIN_SLUG}.php"
ADMIN_USER="admin"
ADMIN_PASS="adminpass123!"
ADMIN_EMAIL="admin@example.com"
SITE_TITLE="Plugin Debug"
```

If the main plugin file is not `your-plugin-slug.php`, set `PLUGIN_BASENAME` explicitly.

## Standard Workflow

### 1. Build the installable zip

Use the plugin’s real packaging command, not a hand-made archive.

```bash
npm run dist
```

Confirm the zip exists:

```bash
ls -l "$ZIP_PATH"
```

### 2. Start an isolated WordPress stack on unused ports

Use a fully separate compose file or project name. Do not reuse the repo’s default ports if other local stacks are already running.

Example temporary compose file:

```yaml
services:
  db:
    image: mysql:8.0
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wp
      MYSQL_PASSWORD: wp
      MYSQL_ROOT_PASSWORD: root
    ports:
      - "33069:3306"
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "-proot"]
      interval: 3s
      timeout: 3s
      retries: 40

  wordpress:
    image: wordpress:php8.2-apache
    depends_on:
      db:
        condition: service_healthy
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_USER: wp
      WORDPRESS_DB_PASSWORD: wp
      WORDPRESS_DB_NAME: wordpress
    ports:
      - "8089:80"
```

Start it:

```bash
docker compose -p "$PROJECT" -f /tmp/${PROJECT}-compose.yml up -d db wordpress
```

Verify the site is reachable:

```bash
curl -I --max-time 15 "$SITE_URL/"
```

Expected first response on a clean site is a redirect to `/wp-admin/install.php`.

### 3. Complete WordPress install non-interactively

Inspect the install form if needed:

```bash
curl -s --max-time 15 "$SITE_URL/wp-admin/install.php?step=1" | sed -n '1,220p'
```

Install:

```bash
curl -s -L --max-time 20 \
  -d "weblog_title=${SITE_TITLE// /+}&user_name=${ADMIN_USER}&admin_password=${ADMIN_PASS//\!/\\!}&admin_password2=${ADMIN_PASS//\!/\\!}&pw_weak=1&admin_email=${ADMIN_EMAIL//@/%40}&blog_public=0&Submit=Install+WordPress&language=" \
  "$SITE_URL/wp-admin/install.php?step=2"
```

### 4. Install the built zip into the container

Copy and unzip the packaged plugin into the real plugins directory:

```bash
docker cp "$ZIP_PATH" "${PROJECT}-wordpress-1:/tmp/${PLUGIN_SLUG}.zip"
docker exec "${PROJECT}-wordpress-1" bash -lc "apt-get update -qq >/dev/null && apt-get install -y -qq unzip >/dev/null && unzip -qo /tmp/${PLUGIN_SLUG}.zip -d /var/www/html/wp-content/plugins"
```

Verify the unpacked plugin directory exists:

```bash
docker exec "${PROJECT}-wordpress-1" bash -lc "ls -1 /var/www/html/wp-content/plugins"
```

### 5. Activate the plugin

Preferred method if WP-CLI is available in the container:

```bash
docker exec "${PROJECT}-wordpress-1" bash -lc "wp plugin activate '${PLUGIN_BASENAME}' --path=/var/www/html"
```

Fallback method without WP-CLI:

```bash
docker exec "${PROJECT}-db-1" mysql -uroot -proot -e "
USE wordpress;
UPDATE wp_options
SET option_value='a:1:{i:0;s:${#PLUGIN_BASENAME}:\"${PLUGIN_BASENAME}\";}'
WHERE option_name='active_plugins';
SELECT option_name, option_value
FROM wp_options
WHERE option_name='active_plugins';
"
```

If the plugin depends on defaults or options, set them now.

Example:

```bash
docker exec "${PROJECT}-db-1" mysql -uroot -proot -e "
USE wordpress;
INSERT INTO wp_options (option_name, option_value, autoload)
SELECT 'my_plugin_enabled_post_types', 'a:2:{i:0;s:4:\"post\";i:1;s:4:\"page\";}', 'yes'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM wp_options WHERE option_name='my_plugin_enabled_post_types'
);
"
```

### 6. Add a temporary autologin MU-plugin

This avoids fighting the login form in headless browser runs.

Important:

- Hook it on `init`, not too early.
- Set a real auth cookie.
- Remove it after the repro.

Template:

```php
<?php
defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['debug_login'] ) || '1' !== $_GET['debug_login'] ) {
			return;
		}

		wp_set_current_user( 1 );
		wp_set_auth_cookie( 1, true );

		if ( ! headers_sent() ) {
			wp_safe_redirect( remove_query_arg( 'debug_login' ) );
			exit;
		}
	},
	1
);
```

Install it:

```bash
docker exec "${PROJECT}-wordpress-1" bash -lc "mkdir -p /var/www/html/wp-content/mu-plugins"
docker cp /tmp/debug-autologin.php "${PROJECT}-wordpress-1:/var/www/html/wp-content/mu-plugins/debug-autologin.php"
```

### 7. Capture screenshots and rendered DOM with headless Chrome

Use a fresh user-data directory each run to avoid stale cookies or cached admin assets.

Screenshot:

```bash
rm -rf /tmp/${PROJECT}-chrome
mkdir -p /tmp/${PROJECT}-chrome
google-chrome \
  --headless \
  --disable-gpu \
  --no-sandbox \
  --user-data-dir=/tmp/${PROJECT}-chrome \
  --virtual-time-budget=12000 \
  --window-size=1600,1400 \
  --screenshot=/tmp/${PROJECT}-page.png \
  "${SITE_URL}/wp-admin/post-new.php?post_type=page&debug_login=1"
```

Rendered DOM:

```bash
rm -rf /tmp/${PROJECT}-chrome-dom
mkdir -p /tmp/${PROJECT}-chrome-dom
google-chrome \
  --headless \
  --disable-gpu \
  --no-sandbox \
  --user-data-dir=/tmp/${PROJECT}-chrome-dom \
  --virtual-time-budget=12000 \
  --dump-dom \
  "${SITE_URL}/wp-admin/post-new.php?post_type=page&debug_login=1" \
  > /tmp/${PROJECT}-page.html
```

Useful follow-up checks:

```bash
rg -n "your-script-handle|your-global-vars|your-button-id|your-container-id" /tmp/${PROJECT}-page.html
```

Important notes for editor testing:

- Test both a brand-new editor screen and an existing content edit screen.
- `post-new.php` can trigger onboarding, starter-pattern, or template-picker overlays that hide the actual canvas UI.
- `post.php?post=<id>&action=edit` is often the better truth source for title-adjacent controls, because the real editor state is already established.
- If a control appears missing on a new page, first determine whether it is truly absent or simply behind an onboarding modal.

To create a simple draft page for existing-page checks without WP-CLI, insert it directly in MySQL:

```bash
docker exec "${PROJECT}-db-1" mysql -uroot -proot -e "
USE wordpress;
INSERT INTO wp_posts (
  post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
  post_status, comment_status, ping_status, post_password, post_name, to_ping,
  pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent,
  guid, menu_order, post_type, post_mime_type, comment_count
) VALUES (
  1, NOW(), UTC_TIMESTAMP(), '', 'Release Verification Page', '',
  'draft', 'closed', 'closed', '', 'release-verification-page', '',
  '', NOW(), UTC_TIMESTAMP(), '', 0,
  '', 0, 'page', '', 0
);
SELECT LAST_INSERT_ID() AS post_id;
"
```

Then inspect:

```bash
google-chrome \
  --headless \
  --disable-gpu \
  --no-sandbox \
  --user-data-dir=/tmp/${PROJECT}-existing \
  --virtual-time-budget=12000 \
  --screenshot=/tmp/${PROJECT}-existing.png \
  "${SITE_URL}/wp-admin/post.php?post=<POST_ID>&action=edit&debug_login=1"
```

### 8. Inspect iframe-based Gutenberg canvases when needed

Modern Gutenberg often renders post content inside `iframe[name="editor-canvas"]`.

That means:

- A top-document DOM dump does not include the title field or block canvas content.
- A selector that worked in older Gutenberg may never find the title block now.
- A button can be correctly injected into the canvas and still not appear in the top document HTML.
- A screenshot can still look wrong if a starter-pattern or onboarding modal is covering the canvas.

When you need to inspect the iframe canvas itself, create a same-origin helper page and let it read the iframe document.

Template:

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Iframe Inspect</title>
</head>
<body>
<pre id="output">waiting...</pre>
<script>
(function () {
  var iframe = document.createElement('iframe');
  var output = document.getElementById('output');
  iframe.style.width = '1600px';
  iframe.style.height = '1400px';
  iframe.src = '/wp-admin/post-new.php?post_type=page&debug_login=1';
  document.body.appendChild(iframe);

  function inspect() {
    try {
      var doc = iframe.contentDocument;
      var canvas = doc && doc.querySelector('iframe[name="editor-canvas"]');
      var canvasDoc = canvas && canvas.contentDocument;
      var result = {
        topButton: !!doc.querySelector('#your-button-id'),
        canvasReady: !!canvasDoc,
        canvasTarget: !!(canvasDoc && canvasDoc.querySelector('.editor-post-title__input, h1.wp-block-post-title')),
        canvasButton: !!(canvasDoc && canvasDoc.querySelector('#your-button-id'))
      };
      output.textContent = JSON.stringify(result, null, 2);
    } catch (error) {
      output.textContent = JSON.stringify({ error: String(error) }, null, 2);
    }
  }

  iframe.addEventListener('load', function () {
    setTimeout(inspect, 12000);
  });
}());
</script>
</body>
</html>
```

Load that helper through the same site origin, not from `file://`.

Then dump the helper page DOM:

```bash
google-chrome \
  --headless \
  --disable-gpu \
  --no-sandbox \
  --user-data-dir=/tmp/${PROJECT}-inspect \
  --virtual-time-budget=12000 \
  --dump-dom \
  "${SITE_URL}/wp-content/plugins/current-plugin/debug-iframe-inspect.html" \
  > /tmp/${PROJECT}-inspect.html
```

### 9. Check the right logs

Container logs:

```bash
docker compose -p "$PROJECT" -f /tmp/${PROJECT}-compose.yml logs --tail=120 wordpress
docker compose -p "$PROJECT" -f /tmp/${PROJECT}-compose.yml logs --tail=120 db
```

Apache and WordPress debug logs:

```bash
docker exec "${PROJECT}-wordpress-1" bash -lc "tail -n 120 /var/log/apache2/error.log 2>/dev/null || true; echo '===WP DEBUG==='; tail -n 120 /var/www/html/wp-content/debug.log 2>/dev/null || true"
```

Common findings:

- PHP fatals in a plugin bootstrap path
- JS loaded but selectors targeting the wrong document
- Fresh-install options missing or not persisted
- Packaged zip missing an asset or script
- Browser cache masking a changed asset when the version string did not change
- Onboarding or starter-pattern modals making a correctly injected canvas control look missing

### 10. Clean up

Remove the temporary stack when done:

```bash
docker compose -p "$PROJECT" -f /tmp/${PROJECT}-compose.yml down -v
```

Remove temporary local artifacts:

```bash
rm -rf /tmp/${PROJECT}-chrome /tmp/${PROJECT}-chrome-dom /tmp/${PROJECT}-inspect
rm -f /tmp/${PROJECT}-page.png /tmp/${PROJECT}-page.html /tmp/${PROJECT}-inspect.html
```

Remove the MU-plugin autologin hook from the container if you are leaving the stack running.

## Rules That Matter

- Test the packaged zip if the bug might involve installability, assets, folder names, or release output.
- Use isolated ports and a unique compose project name every time.
- Assume Gutenberg may be iframe-based.
- Treat `post-new.php` and `post.php?post=<id>&action=edit` as different test cases.
- Do not trust top-document DOM dumps alone for editor issues.
- Use headless browser screenshots plus rendered DOM plus container logs together.
- If the issue only reproduces on a fresh site, do not switch back to an existing long-lived local install until you understand why.

## Minimal Decision Tree

If the plugin does not appear at all:

- Check activation.
- Check zip contents.
- Check enqueue conditions.
- Check fresh-install options.

If the script loads but UI is missing:

- Check selectors.
- Check whether the target lives in the top document or an iframe.
- Check for JS runtime errors before the insertion code runs.

If the UI appears on an old local site but not a clean site:

- Compare saved options.
- Compare editor mode.
- Compare installed zip vs mounted working tree.

If the UI exists but is hidden:

- Check the live screenshot.
- Check modal overlays, onboarding screens, and collapsed panels.
- Check CSS positioning and z-index against the real rendered layout.
- Re-test on an existing edited object, not just a brand-new editor screen.
