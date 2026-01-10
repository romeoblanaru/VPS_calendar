# Get Voice Config Webhook Documentation

## Overview
The `get_voice_config.php` webhook retrieves voice configuration settings for Pi stations based on phone number or IP address. It provides TTS/STT model information, language settings, and other voice-related parameters for each workpoint.

**Key Features**: 
- **Workpoint Identification**: Finds workpoint by phone number or IP address
- **Default Fallback**: Returns lowest ID configuration if no match found
- **Security**: API keys hidden by default (optional exposure with flag)
- **Complete Voice Settings**: TTS model, STT model, language, welcome message
- **JSON Response Format**: Structured responses for both success and error cases
- **Database Integration**: Links with workpoints table for comprehensive data

## Endpoint

### URL
```
GET: /webhooks/get_voice_config.php
```

### Method
- **GET** only

### Content-Type
```
application/json
```

### Authentication
None required

## Parameters

### Optional Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `phone_nr` | string | Phone number to identify workpoint | `+370 600 12345` |
| `ip` | string | IP address to identify workpoint | `192.168.1.100` |
| `include_key` | string | Include TTS secret key in response (set to '1') | `1` |

### Parameter Details

#### Search Priority
1. **Phone Number**: First attempts to find workpoint by phone_nr
2. **IP Address**: If phone_nr not found/provided, searches by IP
3. **Default**: If neither match, returns configuration with lowest workpoint ID

#### Security Parameter
- `include_key=1`: Exposes the TTS secret key in response (use with caution)
- Default behavior: API keys are hidden for security

## Usage Examples

### Basic Request by Phone Number
```
GET /webhooks/get_voice_config.php?phone_nr=+370 600 12345
```

### Request by IP Address
```
GET /webhooks/get_voice_config.php?ip=192.168.1.100
```

### Request with API Key Exposure
```
GET /webhooks/get_voice_config.php?phone_nr=+370 600 12345&include_key=1
```

### Default Configuration Request
```
GET /webhooks/get_voice_config.php
```

### cURL Examples
```bash
# Get config by phone number
curl "http://yourdomain.com/webhooks/get_voice_config.php?phone_nr=+370 600 12345"

# Get config by IP with API key
curl "http://yourdomain.com/webhooks/get_voice_config.php?ip=192.168.1.100&include_key=1"

# Get default configuration
curl "http://yourdomain.com/webhooks/get_voice_config.php"
```

## Response Format

### Success Response (HTTP 200)
```json
{
    "success": true,
    "message": "Voice configuration retrieved successfully",
    "search_method": "phone_number",
    "data": {
        "workpoint_id": 3,
        "workpoint_name": "Reception Desk",
        "phone_number": "+370 600 12345",
        "ip_address": "192.168.1.100",
        "tts_model": "openai",
        "tts_access_link": "https://api.openai.com/v1/audio/speech",
        "stt_model": "WHISPER_BASE",
        "language": "lt",
        "welcome_message": "Sveiki! Jūs susisiekėte su mūsų sistema...",
        "answer_after_rings": 3,
        "voice_settings": {
            "voice": "alloy",
            "speed": 1.0,
            "pitch": 1.0
        },
        "vad_threshold": 0.5,
        "silence_timeout": 500,
        "audio_format": "16kHz_16bit_mono",
        "buffer_size": 1000,
        "is_active": true,
        "updated_at": "2025-01-15 14:30:00"
    }
}
```

### Success Response with API Key (include_key=1)
```json
{
    "success": true,
    "message": "Voice configuration retrieved successfully",
    "search_method": "ip_address",
    "data": {
        "workpoint_id": 3,
        "workpoint_name": "Reception Desk",
        "phone_number": "+370 600 12345",
        "ip_address": "192.168.1.100",
        "tts_model": "openai",
        "tts_access_link": "https://api.openai.com/v1/audio/speech",
        "tts_secret_key": "sk-proj-abc123...",
        "stt_model": "WHISPER_BASE",
        "language": "lt",
        "welcome_message": "Sveiki! Jūs susisiekėte su mūsų sistema...",
        "answer_after_rings": 3,
        "voice_settings": {
            "voice": "alloy",
            "speed": 1.0,
            "pitch": 1.0
        },
        "vad_threshold": 0.5,
        "silence_timeout": 500,
        "audio_format": "16kHz_16bit_mono",
        "buffer_size": 1000,
        "is_active": true,
        "updated_at": "2025-01-15 14:30:00"
    }
}
```

### Error Response - No Configuration Found (HTTP 200)
```json
{
    "success": false,
    "error": "No voice configuration found for workpoint",
    "workpoint_id": 5,
    "data": null
}
```

### Error Response - No Workpoints (HTTP 200)
```json
{
    "success": false,
    "error": "No active workpoints found",
    "data": null
}
```

### Database Error Response (HTTP 200)
```json
{
    "success": false,
    "error": "Database error: Connection failed",
    "data": null
}
```

## Response Fields Description

### Main Response Structure
- `success`: Boolean indicating if request was successful
- `message`: Human-readable success message
- `search_method`: How the workpoint was identified ("phone_number", "ip_address", "default")
- `data`: Contains the voice configuration details (null on error)

### Voice Configuration Fields
- `workpoint_id`: ID of the workpoint
- `workpoint_name`: Display name of the workpoint
- `phone_number`: Phone number associated with workpoint
- `ip_address`: IP address of the workpoint
- `tts_model`: Text-to-speech model name (openai, liepa, etc.)
- `tts_access_link`: API endpoint URL for TTS service
- `tts_secret_key`: API authentication key (only if include_key=1)
- `stt_model`: Speech-to-text model (WHISPER_TINY, WHISPER_BASE, NULL)
- `language`: Language code (lt, en, ro, es)
- `welcome_message`: Custom greeting message for this workpoint
- `answer_after_rings`: Number of rings before system answers the call
- `voice_settings`: JSON object with voice parameters (speed, pitch, voice type)
- `vad_threshold`: Voice Activity Detection sensitivity (0.00-1.00)
- `silence_timeout`: Milliseconds of silence before end-of-speech detection
- `audio_format`: Audio format specification
- `buffer_size`: Audio buffer size in milliseconds
- `is_active`: Whether this configuration is active
- `updated_at`: Last update timestamp

## Database Structure

### Tables Used
1. **`voice_config`**: Main configuration table
2. **`workpoints`**: Workpoint information (joined for additional data)

### Key Database Fields
| Field | Type | Description | Source Table |
|-------|------|-------------|--------------|
| `workpoint_id` | INT | Foreign key to workpoints | voice_config |
| `tts_model` | VARCHAR(100) | TTS model identifier | voice_config |
| `tts_access_link` | VARCHAR(500) | TTS API endpoint | voice_config |
| `tts_secret_key` | VARCHAR(255) | TTS API key | voice_config |
| `stt_model` | ENUM | STT model type | voice_config |
| `language` | VARCHAR(10) | Language code | voice_config |
| `welcome_message` | TEXT | Custom welcome message | voice_config |
| `answer_after_rings` | INT | Number of rings before answering | voice_config |
| `voice_settings` | JSON | Additional voice parameters | voice_config |
| `vad_threshold` | DECIMAL(3,2) | VAD sensitivity | voice_config |
| `silence_timeout` | INT | Silence detection timeout | voice_config |
| `audio_format` | VARCHAR(50) | Audio format spec | voice_config |
| `buffer_size` | INT | Buffer size in ms | voice_config |
| `is_active` | TINYINT(1) | Configuration active flag | voice_config |
| `name` | VARCHAR | Workpoint display name | workpoints |
| `phone_number` | VARCHAR | Workpoint phone | workpoints |
| `ip_address` | VARCHAR | Workpoint IP | workpoints |

## Business Logic

### Workpoint Resolution Logic
1. **Phone Number Search**: Exact match on workpoints.phone_number
2. **IP Address Search**: Exact match on workpoints.ip_address
3. **Default Fallback**: Lowest ID from active workpoints
4. **Active Filter**: Only considers workpoints with is_active = 1

### Security Features
- **API Key Protection**: Secret keys hidden by default
- **Explicit Key Exposure**: Requires include_key=1 parameter
- **Input Sanitization**: All parameters are trimmed and validated
- **SQL Injection Protection**: Uses prepared statements

### Voice Settings Processing
- **JSON Parsing**: Automatically decodes voice_settings JSON field
- **Type Conversion**: Ensures proper data types for numeric fields
- **Null Handling**: Gracefully handles missing or null values

## Configuration Examples

### TTS Models Supported
- **openai**: OpenAI TTS API
- **liepa**: Lithuanian Liepa TTS
- **azure**: Microsoft Azure Speech Services
- **google**: Google Cloud Text-to-Speech

### STT Models Available
- **WHISPER_TINY**: Lightweight Whisper model
- **WHISPER_BASE**: Standard Whisper model
- **NULL**: No STT processing

### Language Codes
- **lt**: Lithuanian (primary)
- **en**: English
- **ro**: Romanian
- **es**: Spanish

### Voice Settings Examples
```json
{
    "voice": "alloy",
    "speed": 1.0,
    "pitch": 1.0,
    "model": "tts-1"
}
```

```json
{
    "voice": "liepa_female",
    "speed": 0.9,
    "emphasis": "moderate"
}
```

## Error Handling

### Common Error Scenarios
1. **No Active Workpoints**: Database has no active workpoints
2. **Configuration Missing**: Workpoint exists but no voice config
3. **Database Connection**: Database connectivity issues
4. **Invalid JSON**: Corrupted voice_settings field

### Error Response Structure
All errors include:
- `success`: Always false
- `error`: Human-readable error description
- `data`: Always null
- Optional context fields (workpoint_id, etc.)

## Integration Examples

### JavaScript/AJAX
```javascript
// Get voice config by phone number
fetch('/webhooks/get_voice_config.php?phone_nr=+370 600 12345')
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('TTS Model:', data.data.tts_model);
        console.log('Language:', data.data.language);
        console.log('Welcome Message:', data.data.welcome_message);
        
        // Use voice settings
        const voiceSettings = data.data.voice_settings;
        console.log('Voice Speed:', voiceSettings.speed);
    } else {
        console.error('Config not found:', data.error);
    }
});
```

### PHP cURL
```php
// Get voice config with API key
$phone_nr = urlencode('+370 600 12345');
$url = "http://yourdomain.com/webhooks/get_voice_config.php?phone_nr={$phone_nr}&include_key=1";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['success']) {
    $config = $result['data'];
    echo "TTS Model: " . $config['tts_model'] . "\n";
    echo "API Key: " . $config['tts_secret_key'] . "\n";
    echo "Language: " . $config['language'] . "\n";
} else {
    echo "Error: " . $result['error'] . "\n";
}
```

### Python Requests
```python
import requests

# Get default configuration
response = requests.get('http://yourdomain.com/webhooks/get_voice_config.php')
data = response.json()

if data['success']:
    config = data['data']
    print(f"Workpoint: {config['workpoint_name']}")
    print(f"TTS Model: {config['tts_model']}")
    print(f"STT Model: {config['stt_model']}")
    print(f"Language: {config['language']}")
    
    # Process voice settings
    voice_settings = config['voice_settings']
    if voice_settings:
        print(f"Voice: {voice_settings.get('voice', 'default')}")
        print(f"Speed: {voice_settings.get('speed', 1.0)}")
else:
    print(f"Error: {data['error']}")
```

## Testing

### Test Scenarios
1. **Valid Phone Number**: Test with existing workpoint phone
2. **Valid IP Address**: Test with existing workpoint IP
3. **Non-existent Identifiers**: Test with unknown phone/IP
4. **Default Fallback**: Test without parameters
5. **API Key Exposure**: Test with include_key parameter
6. **Inactive Workpoints**: Test with inactive workpoint data

### Example Test Cases
```bash
# Test 1: Valid phone number
curl "http://your-domain.com/webhooks/get_voice_config.php?phone_nr=+370 600 12345"

# Test 2: Valid IP address with API key
curl "http://your-domain.com/webhooks/get_voice_config.php?ip=192.168.1.100&include_key=1"

# Test 3: Non-existent phone (should return default)
curl "http://your-domain.com/webhooks/get_voice_config.php?phone_nr=+999 999 9999"

# Test 4: Default configuration
curl "http://your-domain.com/webhooks/get_voice_config.php"

# Test 5: Invalid parameters
curl "http://your-domain.com/webhooks/get_voice_config.php?invalid_param=test"
```

## Security Considerations

1. **API Key Protection**: Secret keys hidden by default
2. **Explicit Authorization**: Requires include_key=1 for key exposure
3. **Input Sanitization**: All parameters are sanitized
4. **SQL Injection Protection**: Uses prepared statements
5. **Error Information**: No sensitive data in error messages

## Performance Considerations

- **Database Indexes**: Workpoints table should have indexes on phone_number and ip_address
- **Prepared Statements**: Efficient query execution
- **JSON Parsing**: Minimal overhead for voice_settings processing
- **Caching**: Consider implementing caching for frequently accessed configs

## Pi Station Integration

### Typical Usage Flow
1. **Pi Station Startup**: Request configuration using phone_nr or ip
2. **Configuration Application**: Apply TTS/STT settings
3. **Welcome Message**: Use custom welcome message
4. **Voice Parameters**: Apply voice_settings for TTS
5. **Audio Processing**: Use VAD threshold and buffer settings

### Configuration Mapping
```javascript
// Example Pi station configuration mapping
const config = voiceConfigResponse.data;

// TTS Configuration
const ttsConfig = {
    model: config.tts_model,
    apiUrl: config.tts_access_link,
    apiKey: config.tts_secret_key, // if included
    language: config.language,
    voice: config.voice_settings.voice,
    speed: config.voice_settings.speed
};

// STT Configuration  
const sttConfig = {
    model: config.stt_model,
    language: config.language
};

// Audio Processing
const audioConfig = {
    vadThreshold: config.vad_threshold,
    silenceTimeout: config.silence_timeout,
    format: config.audio_format,
    bufferSize: config.buffer_size
};
```

## Related Webhooks

- **`dynamic_variables_for_start.php`**: Get startup configuration variables
- **`check_ip.php`**: Verify IP-based access permissions
- **`insert_vpn_ip.php`**: Register new VPN IP addresses

## Maintenance

Regular maintenance should include:
- Monitoring voice configuration usage patterns
- Updating TTS/STT model configurations as needed
- Reviewing and optimizing database queries
- Testing API key security measures
- Validating voice_settings JSON structure integrity

## Support

For issues or questions regarding this webhook:
1. Verify workpoint exists and is active in database
2. Check voice_config table for corresponding records
3. Test with include_key=1 to verify API key exposure
4. Review database connection settings
5. Contact development team for configuration assistance