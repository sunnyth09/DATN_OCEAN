---
name: Vivid Clarity (Revised)
colors:
  surface: '#f8f9fa'
  surface-dim: '#d9dadb'
  surface-bright: '#ffffff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f4f5'
  surface-container: '#edeeef'
  surface-container-high: '#e7e8e9'
  surface-container-highest: '#e1e3e4'
  on-surface: '#191c1d'
  on-surface-variant: '#594044'
  inverse-surface: '#2e3132'
  inverse-on-surface: '#f0f1f2'
  outline: '#e9ecef'
  outline-variant: '#f1f3f5'
  surface-tint: '#e63b6f'
  primary: '#e63b6f'
  on-primary: '#ffffff'
  primary-container: '#ffd9de'
  on-primary-container: '#3f0016'
  secondary: '#2d3436'
  on-secondary: '#ffffff'
  secondary-container: '#dae1e3'
  on-secondary-container: '#5d6466'
  background: '#ffffff'
  on-background: '#191c1d'
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 8px
  sm: 16px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: auto
---

## Brand & Style

The design system is defined by an energetic, modern, and high-clarity aesthetic. It balances the high-octane energy of its primary accent with a clean, breathable environment. The brand personality is confident and approachable, targeting a demographic that values speed, precision, and visual vibrancy. 

The design style follows **Modern Minimalism** with a focus on high-impact accents. By utilizing vast amounts of white space and a crisp light-mode palette, we allow the primary pink to act as a functional beacon for interaction and navigation. The emotional response should be one of freshness, reliability, and effortless movement.

## Colors

This design system utilizes a high-contrast Light Mode palette to maximize legibility and energy.

- **Primary (#E63B6F):** A deep, vibrant pink used exclusively for primary actions (Call to Actions), active states, and key brand moments.
- **Secondary/Text (#2D3436):** A deep charcoal used for headings, primary body text, and secondary UI elements to ensure WCAG AA/AAA compliance and prevent visual fatigue.
- **Surface (#F8F9FA):** An ultra-light gray used for section backgrounds, input fields, and subtle containers to create depth without adding visual noise.
- **Background (#FFFFFF):** Pure white is the foundation for the main content areas, ensuring the interface feels airy and spacious.
- **Muted Text (#636E72):** A mid-tone gray for secondary information and labels.

## Typography

The design system uses **Inter** across all levels. As a highly optimized neo-grotesque sans-serif, Inter provides maximum legibility for digital interfaces, making it perfect for data-heavy applications and clean e-commerce displays.

Headlines use heavy weights and slight negative letter spacing to create a compact, dynamic look. Body text is prioritized for readability with generous line heights. Labels and small UI elements use a semi-bold or bold weight to ensure they remain legible against the light gray surfaces.

## Layout & Spacing (Crucial for preventing Overflow)

This design system operates on an **8px linear scale** for consistent spacing and alignment. 

- **Box Model:** All elements MUST use `box-sizing: border-box` to prevent padding and borders from breaking widths.
- **Container Constraints:** Utilizes a **12-column fluid grid** for desktop with a strict `max-width` of 1440px and `margin: 0 auto` to prevent stretching on ultra-wide screens. For mobile, it scales down to a **4-column grid**.
- **Margins:** 24px horizontal padding on tablet/desktop containers and 16px on mobile to safely contain content.
- **Vertical Rhythm:** Strict adherence to `lg` (40px) or `xl` (64px) spacing between major sections to ensure the layout has "breathing room" (whitespace).
- **Component Padding:** Internal padding follows the `sm` (16px) and `md` (24px) tokens. 

## Elevation & Depth

To maintain the "modern and dynamic" feel in Light Mode, the design system avoids heavy shadows, using a combination of **Tonal Layers** and **Ambient Shadows**.

1.  **Level 0 (Background):** Pure #FFFFFF.
2.  **Level 1 (Subtle Inset):** #F8F9FA used for backgrounds of large sections or input fields to create a "recessed" look.
3.  **Level 2 (Cards/Buttons):** Soft, high-diffusion shadows. Use a 10% opacity primary-tinted gray shadow (e.g., `rgba(45, 52, 70, 0.08)`) with a 15px blur and 4px Y-offset.
4.  **Level 3 (Modals/Popovers):** Deeper shadows with a 30px blur to clearly separate the element from the page flow.

Low-contrast outlines (1px solid #E9ECEF) are used on Level 2 elements to define boundaries without relying solely on shadows.

## Components

### Buttons
*Rule: Never use fixed heights (e.g., height: 60px) for buttons. Button size is strictly determined by typography line-height and padding to maintain proportion.*
- **Primary:** Solid #E63B6F fill with #FFFFFF text. Padding `12px 24px`. Level 2 shadow. Border-radius `8px`.
- **Secondary:** Transparent fill with a 1px solid #E9ECEF border and #2D3436 text. No shadow.
- **Ghost:** No border or fill; text in #2D3436. Hover state adds #F8F9FA background.

### Product Cards
- **Background:** #FFFFFF.
- **Border:** 1px solid #F1F3F5.
- **Shadow:** Level 2 ambient shadow.
- **Content:** Headline in #2D3436. Price uses #E63B6F for emphasis. Internal padding `md` (24px). Image area utilizes `aspect-ratio` to ensure uniform sizes.

### Navbar / Aside Menu
- **Surface:** #FFFFFF.
- **Border:** 1px solid #F8F9FA.
- **Links:** Default text #2D3436. Active state uses #E63B6F text with a subtle #FFF0F4 background block for clear contextual indication.

### Input Fields
- **Surface:** #F8F9FA.
- **Border:** 1px solid #E9ECEF.
- **Focus State:** 1px solid #E63B6F. *(Removed outer glow to maintain a modern, crisp UI).*

### Chips & Badges
- **Style:** Pill-shaped (rounded-full).
- **Color:** Soft gray background (#F8F9FA) with #2D3436 text for standard tags. Use soft pink (#FFF0F4) with #E63B6F text *only* for high-priority alerts (e.g., "Flash Sale", "VIP").