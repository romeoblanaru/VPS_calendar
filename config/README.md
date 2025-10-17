# Google Calendar Integration Setup

## Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Note your project ID

## Step 2: Enable Google Calendar API

1. Go to "APIs & Services" > "Library"
2. Search for "Google Calendar API"
3. Click "Enable"

## Step 3: Create OAuth 2.0 Credentials

1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "OAuth 2.0 Client IDs"
3. Choose "Web application"
4. Name it (e.g., "Calendar Booking Integration")
5. Add authorized redirect URI:
   - For localhost: `http://localhost/calendar/admin/google_oauth_callback.php`
   - For production: `https://yourdomain.com/calendar/admin/google_oauth_callback.php`

## Step 4: Configure Credentials

1. Copy `google_oauth.json.template` to `google_oauth.json`
2. Replace the placeholder values with your actual credentials:
   - `YOUR_GOOGLE_CLIENT_ID_HERE` → Your Client ID from Google Console
   - `YOUR_GOOGLE_CLIENT_SECRET_HERE` → Your Client Secret from Google Console
   - `your-project-id` → Your Google Cloud Project ID
3. Update redirect URIs to match your domain

## Step 5: Security

- Add `config/google_oauth.json` to your `.gitignore` file
- Never commit your actual credentials to version control
- Keep your Client Secret secure

## Commands to set up:

```bash
# Copy template
cp config/google_oauth.json.template config/google_oauth.json

# Edit with your credentials
nano config/google_oauth.json
```

## Testing

After setup, go to your booking page and click "Connect to Google Calendar". You should be redirected to Google's authorization page. 