# AI + AR Smart Object Recognition System

Point your phone camera at an object, tap **SCAN**, and the app identifies it with an AI vision model. It then draws an AR-style detection box on the live camera view with an information card (product, manufacturer, specification, description, confidence).

Built with plain PHP, MySQL (optional), HTML, CSS and vanilla JavaScript. No frameworks, no Composer.

---

## 1. Project Overview

```
Phone camera -> capture frame -> api/recognize.php -> Gemini / Agnes
             -> validated JSON -> AR box + info card
```

The scan result always comes from a real AI call. There is no demo data: if no provider is configured, the scanner says **"AI provider is not configured."**

## 2. Features

- Live rear-camera preview with scan frame, tap-to-focus and clear status messages
- Canvas capture that crops exactly the visible area, resizes to max 1280 px and compresses to JPEG (0.70)
- Provider modes: **Auto** (Gemini, then Agnes), **Gemini only**, **Agnes only**, with fallback models
- Strict prompt: reads visible text, never invents a model number, returns a generic name when unsure
- All AI output is validated and cleaned (bounding box 0-1000, confidence 0-1, text sanitised)
- AR detection box + expandable info card (tap for details)
- Real progress stages (no fake percentage)
- Admin settings page with Test Connection and a QR code to open the scanner on a phone
- Optional MySQL scan history with search, pagination, delete and clear
- Accessible: ARIA labels, `aria-live` status, keyboard focus styles

## 3. Technology Stack

PHP 8.3+, MySQL 8+ (optional), PDO, cURL, HTML5, CSS3, vanilla JavaScript (MediaDevices, Canvas, Fetch). The only external resources are Google Fonts and the QR code library (cdnjs) used on the settings page.

## 4. Requirements

- PHP 8.3 or newer with the **cURL** extension (enabled by default in XAMPP and cPanel)
- `pdo_mysql` only if you want scan history
- A Gemini API key and/or an Agnes API key
- HTTPS for testing on a phone (see section 7)

## 5. XAMPP Installation

1. Copy the `ai-ar-object-recognition` folder into `C:\xampp\htdocs\`.
2. Start **Apache** (and **MySQL** if you want history) in the XAMPP Control Panel.
3. Open `http://localhost/ai-ar-object-recognition/`. Camera access works on `localhost`.
4. Open `http://localhost/ai-ar-object-recognition/settings.php`, sign in, enter your API key(s) and click **Save settings**, then **Test Connection**.

The `storage/` folder must be writable (it holds saved settings and the error log). On Windows XAMPP this works by default.

### Admin login

The settings and history pages are protected by a login. The username and password hash are in `config/config.php`. To change the password:

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT);"
```

Paste the output into `admin_password_hash` in `config/config.php`.

## 6. cPanel Installation

1. In cPanel **File Manager**, upload the ZIP into `public_html/` (or a sub-folder such as `public_html/ai-ar/`) and **Extract** it. The app works in any sub-folder because it only uses relative URLs.
2. Make sure PHP 8.3+ is selected (**MultiPHP Manager**).
3. Set `storage/` permissions to `755` (or `775` if saving settings fails).
4. Visit `https://your-domain.com/ai-ar/settings.php`, sign in and configure your API key(s).

**Safer option:** if your host lets you, put the whole `config/`, `includes/` and `storage/` folders above `public_html` and adjust the `require` paths. The included `.htaccess` files already block direct web access to those folders on Apache hosts.

## 7. HTTPS Requirement

Browsers only allow camera access in a **secure context**: HTTPS, or `localhost`. Testing from a phone against `http://192.168.x.x/...` will **not** work.

Ways to test on a phone:

- Deploy to cPanel hosting (free SSL via AutoSSL / Let's Encrypt)
- Use an HTTPS tunnel to your XAMPP (for example Cloudflare Tunnel or ngrok) and open the tunnel address on your phone

The scanner shows *"Please use HTTPS to access the camera."* when the page is not secure.

## 8. AI API Configuration

Open **Settings** and fill in:

| Setting | Notes |
|---|---|
| Provider mode | Auto / Gemini / Agnes |
| Gemini API key + model | Default model `gemini-2.5-flash`. Model names change over time: edit them here if Google renames one. |
| Gemini fallback models | One per line, tried if the main model fails |
| Agnes API base URL, key, model | Agnes is assumed to offer an **OpenAI-compatible** API (`POST {base}/chat/completions`). Enter the base URL from your Agnes account. If Agnes uses a different request format, edit only `includes/AgnesVision.php`. |
| Request timeout | 10-120 seconds |

Keys are stored server-side in `storage/settings.php` (blocked from the web, file mode 600). They are never sent to the browser. The Settings page only shows **Key saved** or **Not configured**. You can alternatively put keys in `config/config.php`.

**Test Connection** checks the *saved* settings, so click Save first.

## 9. Database Setup (optional)

Only needed for scan history.

1. Import `database/database.sql` using phpMyAdmin (or `mysql -u root -p < database/database.sql`).
2. Edit `config/config.php`:

```php
'db_enabled' => true,
'db_host' => 'localhost',
'db_name' => 'ai_ar_scanner',
'db_user' => 'root',
'db_pass' => '',
```

3. Open `history.php` (sign in first). The camera image is never stored, only the text result.

On shared hosting, create the database and user in cPanel first and use those names (cPanel adds a prefix such as `account_`).

## 10. Camera Permission

- Tap **Allow** when the browser asks for the camera.
- If you tapped Block: Chrome (Android) lock icon in the address bar > Permissions > Camera > Allow. Safari (iPhone): Settings > Safari > Camera > Allow (or *aA* menu > Website Settings).
- Close other apps that may be using the camera, then press **Try again**.

## 11. Testing

1. Settings > enter a key > Save > **Test Connection** shows a green tick.
2. Open the scanner on a phone, point at a laptop, mouse, bottle, etc., and tap **SCAN**.
3. Check that the green box sits around the object and the card says **Tap for details**.
4. Tap the card to expand it. Tap **SCAN AGAIN** to return to the live camera.
5. In **Auto** mode, test the fallback by temporarily saving a wrong Gemini key (Agnes should take over).

Check on Android Chrome, iPhone Safari, and desktop Chrome/Edge.

## 12. Troubleshooting

| Problem | Fix |
|---|---|
| "Please use HTTPS to access the camera." | Use HTTPS or `localhost` (section 7) |
| "AI provider is not configured." | Add a key in Settings (and the base URL for Agnes) |
| "The AI provider rejected its API key." | Re-enter the key and click Save |
| Model not found in Test Connection | Change the model name in Settings |
| "Could not save" on Settings | Make `storage/` writable |
| Box looks slightly off the object | AI boxes are approximate; the image is frozen after capture so it stays aligned with what was scanned |
| Blank / 500 error | Look at `storage/error.log` |
| "Too many scans" | Built-in limit of 15 scans per minute per IP, in `api/recognize.php` |
| History page empty or errors | Import `database.sql` and set `db_enabled` |

## 13. Security Notes

- API keys live only on the server and are never sent to the browser or logged (log lines are redacted).
- `config/`, `includes/`, `storage/`, `database/` are blocked by `.htaccess` (Apache). On Nginx, add equivalent `deny all` rules.
- Settings and history require a login. Passwords are verified with `password_verify`; forms and APIs use CSRF tokens and `HttpOnly` / `SameSite` session cookies.
- Uploaded images are checked for size, type and dimensions. AI output is treated as untrusted: validated, sanitised, and displayed with `textContent` only.
- The scan endpoint is public so anyone with the link can use your AI quota. A per-IP limit is included; for a public site also set a spending limit in your AI provider console.
- Do not commit `config/config.php` or `storage/` to GitHub (they are in `.gitignore`). **Change the default admin password** before going live.

## 14. Project Folder Structure

```text
ai-ar-object-recognition/
├── index.php            scanner (camera + AR)
├── settings.php         admin login + settings + QR code
├── history.php          optional scan history
├── README.md
├── api/
│   ├── recognize.php    image -> AI -> JSON
│   ├── lookup.php       optional web reference
│   ├── settings.php     save settings / test connection
│   └── history.php      delete / clear history
├── config/
│   ├── config.example.php
│   └── config.php       admin login + optional DB/keys (private)
├── includes/
│   ├── bootstrap.php    autoload, error handling, helpers
│   ├── AppConfig.php  Auth.php  RateLimiter.php
│   ├── AIProvider.php  GeminiVision.php  AgnesVision.php  ProviderException.php
│   ├── ObjectRecognizer.php  ProductResult.php  ProductLookup.php
│   ├── ImageValidator.php
│   └── Database.php  ScanHistory.php
├── assets/
│   ├── css/app.css
│   └── js/ camera.js  ar-overlay.js  scanner.js  settings.js  history.js
├── database/database.sql
└── storage/             saved settings, error log (private, writable)
```

### Note on the web lookup

The optional lookup searches Wikipedia and only attaches a link when the page title contains the manufacturer and most of the product-name words. It never replaces the AI's answer, so an unrelated search hit cannot corrupt the result.
