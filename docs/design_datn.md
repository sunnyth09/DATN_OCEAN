---
name: Vivid Clarity
colors:
  surface: '#f8f9fa'
  surface-dim: '#d9dadb'
  surface-bright: '#f8f9fa'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f4f5'
  surface-container: '#edeeef'
  surface-container-high: '#e7e8e9'
  surface-container-highest: '#e1e3e4'
  on-surface: '#191c1d'
  on-surface-variant: '#594044'
  inverse-surface: '#2e3132'
  inverse-on-surface: '#f0f1f2'
  outline: '#8d6f74'
  outline-variant: '#e1bec3'
  surface-tint: '#b91150'
  primary: '#b50c4d'
  on-primary: '#ffffff'
  primary-container: '#d82f65'
  on-primary-container: '#fffbff'
  inverse-primary: '#ffb2bf'
  secondary: '#586062'
  on-secondary: '#ffffff'
  secondary-container: '#dae1e3'
  on-secondary-container: '#5d6466'
  tertiary: '#006b2d'
  on-tertiary: '#ffffff'
  tertiary-container: '#00873b'
  on-tertiary-container: '#f7fff3'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffd9de'
  primary-fixed-dim: '#ffb2bf'
  on-primary-fixed: '#3f0016'
  on-primary-fixed-variant: '#90003b'
  secondary-fixed: '#dde4e6'
  secondary-fixed-dim: '#c1c8ca'
  on-secondary-fixed: '#161d1f'
  on-secondary-fixed-variant: '#41484a'
  tertiary-fixed: '#84fb9b'
  tertiary-fixed-dim: '#68de82'
  on-tertiary-fixed: '#002109'
  on-tertiary-fixed-variant: '#005321'
  background: '#f8f9fa'
  on-background: '#191c1d'
  surface-variant: '#e1e3e4'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Plus Jakarta Sans
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

- **Primary (#E63B6F):** A deep, vibrant pink used for primary actions, progress indicators, and key brand moments.
- **Secondary/Text (#2D3436):** A deep charcoal used for headings and primary body text to ensure WCAG AA/AAA compliance on light backgrounds.
- **Surface (#F8F9FA):** An ultra-light gray used for section backgrounds, input fields, and subtle containers to create depth without adding visual noise.
- **Background (#FFFFFF):** Pure white is the foundation for the main content areas, ensuring the interface feels airy and spacious.
- **Muted Text (#636E72):** A mid-tone gray for secondary information and labels.

## Typography

The design system uses **Plus Jakarta Sans** across all levels to maintain a friendly yet professional tone. The geometric nature of the font complements the modern layout.

Headlines use heavy weights and slight negative letter spacing to create a compact, "editorial" look that feels dynamic. Body text is prioritized for readability with generous line heights. Labels and small UI elements use a semi-bold or bold weight to ensure they remain legible against the light gray surfaces.

## Layout & Spacing

This design system operates on a **8px linear scale** for consistent spacing and alignment. 

The layout utilizes a **12-column fluid grid** for desktop (max-width 1440px) and a **4-column grid** for mobile devices. Margins are set to 24px on tablet/desktop and 16px on mobile to maximize screen real estate. 

Padding within components (like cards and buttons) should follow the `sm` (16px) and `md` (24px) spacing tokens to maintain a sense of openness. Vertical rhythm is established through consistent `lg` (40px) or `xl` (64px) spacing between major sections.

## Elevation & Depth

To maintain the "modern and dynamic" feel in Light Mode, the design system avoids heavy shadows. Instead, it uses a combination of **Tonal Layers** and **Ambient Shadows**.

1.  **Level 0 (Background):** Pure #FFFFFF.
2.  **Level 1 (Subtle Inset):** #F8F9FA used for backgrounds of large sections or input fields to create a "recessed" look.
3.  **Level 2 (Cards/Buttons):** Soft, high-diffusion shadows. Use a 10% opacity primary-tinted gray shadow (e.g., `rgba(45, 52, 70, 0.08)`) with a 15px blur and 4px Y-offset.
4.  **Level 3 (Modals/Popovers):** Deeper shadows with a 30px blur to clearly separate the element from the page flow.

Low-contrast outlines (1px solid #E9ECEF) are used on Level 2 elements to define boundaries without relying solely on shadows.

## Shapes

The design system utilizes **Rounded (Value 2)** geometry to evoke a friendly and approachable feel. 

- Standard components (buttons, input fields) use a 0.5rem (8px) corner radius.
- Larger containers (product cards, modals) use a 1rem (16px) corner radius.
- Interactive chips or "tag" elements may use a fully rounded (pill-shaped) style to differentiate them from actionable buttons.

## Components

### Buttons
- **Primary:** Solid #E63B6F fill with #FFFFFF text. Heavy weight typography.
- **Secondary:** Transparent fill with a 2px #E63B6F border and #E63B6F text.
- **Ghost:** No border or fill; text in #E63B6F.

### Product Cards
- **Background:** #FFFFFF.
- **Border:** 1px solid #F1F3F5.
- **Shadow:** Level 2 ambient shadow.
- **Content:** Headline in #2D3436, price or accent info in #E63B6F.

### Navbar
- **Surface:** #FFFFFF with a 95% backdrop blur.
- **Bottom Border:** 1px solid #F8F9FA.
- **Links:** #2D3436 with #E63B6F active/hover states.

### Input Fields
- **Surface:** #F8F9FA.
- **Border:** 1px solid #E9ECEF.
- **Focus State:** 2px solid #E63B6F with a soft pink outer glow.

### Chips & Badges
- **Style:** Pill-shaped.
- **Color:** Soft pink background (10% opacity of #E63B6F) with #E63B6F text for high visibility and brand alignment.