# Mockup Templates

Add realistic mockup templates to this directory. The system automatically scans all templates and uses them for mockup generation.

## Template Sources

### Free Resources:
- **Mr.Mockup**: https://mrmockup.com/free-poster-mockups/
- **Freepik**: https://www.freepik.com/free-photos-vectors/poster-mockup
- **Mockupnest**: https://mockupnest.com/free-wall-poster-mockup/
- **Resource Boy**: https://resourceboy.com/mockups/poster/
- **Pixeden**: https://www.pixeden.com/free-graphics
- **GraphicBurger**: https://graphicburger.com/mock-ups/
- **Mockup World**: https://www.mockupworld.co/free/

## Template Format

For each template directory:
1. **template.jpg or template.png** - Mockup image
2. **config.json** - Coordinates and metadata

### config.json Structure:

```json
{
  "name": "Wall Poster A4",
  "size": "A4 (8.3x11.7 in)",
  "file": "template.jpg",
  "insert_area": {
    "x": 245,
    "y": 180,
    "width": 510,
    "height": 720,
    "curvature": 0.15
  },
  "variant": "wall_a4",
  "perspective": false,
  "is_primary": true
}
```

### Field Descriptions:

- **name**: Mockup name displayed to user
- **size**: Product size (optional)
- **file**: Template file name (JPG or PNG)
- **insert_area**: Area where AI portrait will be placed
  - **x**: Top-left corner X coordinate (pixels)
  - **y**: Top-left corner Y coordinate (pixels)
  - **width**: Area width (pixels)
  - **height**: Area height (pixels)
  - **curvature**: Curvature factor for perspective (0.1-0.3, optional)
- **variant**: Unique template ID (can use folder name)
- **perspective**: Enable perspective transformation for curved surfaces (true/false)
- **is_primary**: Show as primary mockup (true/false)

## How to Find Coordinates

### Method 1: Photoshop
1. Open PSD template
2. Select Rectangle Marquee Tool (M)
3. Select the empty poster area
4. Read coordinates from Window > Info panel
   - X, Y: Top-left corner
   - W, H: Width and height

### Method 2: GIMP (Free)
1. Open template in GIMP
2. Select Rectangle Select Tool
3. Select the empty poster area
4. Read coordinates from Tool Options

### Method 3: Online Tools
- Use the included `coordinate-finder.html` tool
- Open in browser, upload template image
- Click and drag to select the area
- config.json is generated automatically

### Method 4: Online Coordinate Picker
- https://www.mobilefish.com/services/record_mouse_coordinates/record_mouse_coordinates.php
- Upload template and mark coordinates with mouse

## Step-by-Step Template Addition

1. **Download Mockup**
   ```
   Download PSD/JPG from Mr.Mockup or other sources
   ```

2. **Create Folder**
   ```bash
   mkdir wall-poster-a4
   cd wall-poster-a4
   ```

3. **Export Template**
   - Export PSD as JPG (quality: 90-100%)
   - File name: `template.jpg`

4. **Find Coordinates**
   - Use Photoshop/GIMP to find empty area coordinates
   - Or use `coordinate-finder.html` tool
   - Note: x, y, width, height

5. **Create config.json**
   ```json
   {
     "name": "Wall Poster A4",
     "size": "A4 (8.3x11.7 in)",
     "file": "template.jpg",
     "insert_area": {
       "x": 245,
       "y": 180,
       "width": 510,
       "height": 720
     },
     "variant": "wall_a4",
     "perspective": false,
     "is_primary": true
   }
   ```

6. **Test**
   - Create a new AI portrait in WordPress
   - Verify mockups are generated

## Folder Structure

```
assets/mockup-templates/
├── README.md
├── coordinate-finder.html
├── wall-poster-a4/
│   ├── template.jpg
│   └── config.json
├── wall-poster-a3/
│   ├── template.jpg
│   └── config.json
├── lifestyle/
│   ├── template.jpg
│   └── config.json
└── mug-print/
    ├── template.jpg
    └── config.json
```

## Recommended Template Types

1. **Wall Posters**
   - Framed wall posters
   - Frameless posters
   - Various sizes (A4, A3, A2)

2. **Lifestyle Scenes**
   - Posters in home decor
   - Posters in office settings
   - Cafe/restaurant scenes

3. **Print Products**
   - Mug prints (with perspective)
   - T-shirt prints
   - Pillow cases
   - Phone cases
   - Tote bags

## Perspective Transformation

For curved surfaces like mugs, enable perspective transformation:

```json
{
  "perspective": true,
  "insert_area": {
    "x": 380,
    "y": 250,
    "width": 340,
    "height": 280,
    "curvature": 0.15
  }
}
```

**Curvature values:**
- `0.1`: Subtle curve (gentle products)
- `0.15`: Medium curve (standard mugs)
- `0.2-0.3`: Strong curve (highly curved surfaces)

The system applies cylindrical projection to wrap the portrait around the curve.

## Notes

- Template files should be high resolution (min 1500px width)
- insert_area aspect ratio should match A4 paper (1:1.414) for posters
- System automatically scales and places AI portrait
- Each folder represents one mockup type
- Adding new templates doesn't require code changes
- Templates are loaded dynamically on each mockup generation

## Troubleshooting

**Mockup not appearing:**
- Check that template.jpg exists in folder
- Verify config.json is valid JSON
- Check error logs for coordinate issues

**Portrait doesn't fit:**
- Adjust insert_area coordinates
- Use coordinate-finder.html to find exact area

**Perspective looks wrong:**
- Adjust curvature value (0.1-0.3)
- Set perspective: false for flat surfaces

**Low quality mockups:**
- Use higher resolution template images
- Export PSD at 90-100% quality
- Ensure insert_area is large enough
