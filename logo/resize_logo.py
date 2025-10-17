from PIL import Image

# Open the original image
img = Image.open('my-bookings_logo.png')
print(f'Original size: {img.size}')
print(f'Mode: {img.mode}')

# Convert to RGBA if not already (for transparency support)
if img.mode != 'RGBA':
    img = img.convert('RGBA')

# Create smaller version (200px wide, maintaining aspect ratio)
width = 200
ratio = width / img.width
height = int(img.height * ratio)
img_small = img.resize((width, height), Image.Resampling.LANCZOS)

# Save transparent version
img_small.save('my-bookings_logo_small_transparent.png', 'PNG')
print(f'Created transparent version: {width}x{height}')

# Create white background version
img_white_bg = Image.new('RGBA', img_small.size, (255, 255, 255, 255))
img_white_bg.paste(img_small, (0, 0), img_small)
img_white_bg = img_white_bg.convert('RGB')  # Convert to RGB for smaller file size
img_white_bg.save('my-bookings_logo_small_white.png', 'PNG')
print(f'Created white background version: {width}x{height}')

print('\nAll versions created successfully!')