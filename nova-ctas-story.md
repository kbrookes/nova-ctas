# Nova CTAs Plugin - User Story and Requirements

## Overview
As a WordPress content manager, I want to automatically insert relevant Call-To-Action (CTA) blocks into my blog posts based on their categories, so that I can guide readers to related pillar pages and increase engagement without manually placing CTAs in each post.

## Current State (v1.1.19)
The plugin currently provides:
- Custom post type for managing CTAs
- Category-based targeting for CTA display
- Automatic insertion of CTAs at specified positions in content
- Visual editor for CTA content and design
- Support for pillar page linking
- Shortcode support for manual CTA placement

## User Stories

### 1. CTA Management
**As a** content manager  
**I want to** create and manage CTAs through a dedicated interface  
**So that** I can maintain a library of reusable CTAs for my content

#### Acceptance Criteria
- [x] Create CTAs with title, content, and button
- [x] Configure CTA design settings (colors, typography, spacing)
- [ ] Preview CTA appearance before publishing (needs implementation)
  - Live preview in editor
  - Mobile/desktop preview toggle
  - Sample content for new CTAs
- [x] Edit existing CTAs without affecting live content
- [x] Organize CTAs by categories
- [ ] Help documentation accessible from admin interface
  - Basic usage instructions
  - Shortcode documentation
  - Examples and best practices

### 2. Automatic CTA Placement
**As a** content manager  
**I want to** have CTAs automatically inserted into relevant posts  
**So that** I can maintain consistent CTA placement without manual intervention

#### Acceptance Criteria
- [x] Configure which categories a CTA should appear in
- [x] Set the position where CTAs appear in content (% through content)
- [x] Option to show CTA at end of content
- [x] Multiple CTAs can appear in the same post
- [x] CTAs maintain proper HTML structure
- [x] No content disruption when inserting CTAs
- [ ] CTA styling properly applied (needs improvement)
  - Styles from CTA manager reflected in front-end
  - Inline styles only loaded on posts with CTAs
  - Mobile-responsive design implementation
- [ ] Shortcode mode support
  - Complete alternative to built-in CTA builder
  - Allow CTAs to be built with external page builders
  - Shortcode handles all CTA elements (design, content, buttons)
  - Only Display and Relationships settings used from Nova CTAs
  - Documentation for shortcode implementation
  - Example templates for popular page builders

### 3. Pillar Page Integration
**As a** content manager  
**I want to** link CTAs to pillar pages  
**So that** I can guide readers through my content hierarchy

#### Acceptance Criteria
- [x] Mark pages as pillar pages
- [x] Select pillar pages as CTA destinations
- [x] Display pillar page status in page lists
- [x] Automatic URL updates if pillar page URL changes

### 4. Analytics Integration
**As a** content strategist  
**I want to** track CTA performance  
**So that** I can measure and improve engagement

#### Acceptance Criteria
- [ ] Plausible Analytics integration
  - Track clicks with post title
  - Track clicks with destination page title
  - Custom event naming
  - Dashboard reporting

## Technical Requirements

### Performance
- Minimize database queries when displaying CTAs
- Prevent infinite loops in content filtering
- Efficient content parsing and CTA insertion
- Clean handling of HTML structure
- Optimize style loading for posts with CTAs

### Security
- Proper nonce verification for forms
- Data sanitization and validation
- Safe HTML handling in CTA content
- Secure meta data handling
- Safe shortcode processing

### Code Quality
- WordPress coding standards compliance
- Proper action/filter hook usage
- Clean separation of concerns
- Comprehensive error handling
- Debug logging for troubleshooting

### Integration
- Compatible with popular page builders
- Works with custom post types
- Respects theme styling
- Mobile-responsive design
- Plausible Analytics compatibility

## Current Issues Addressed
- [x] Fixed CTAs appearing only at bottom of posts
- [x] Fixed missing titles and buttons in CTA display
- [x] Fixed array index error in content processing
- [x] Improved paragraph handling
- [x] Enhanced HTML structure maintenance

## Pending Improvements
- [ ] Enhanced mobile responsiveness
- [ ] Better visual feedback in admin interface
- [ ] Performance optimization for sites with many CTAs
- [ ] Advanced targeting options
- [ ] Help tab implementation
  - Basic usage instructions
  - Shortcode documentation
  - Best practices guide
- [ ] Shortcode mode implementation
  - Full CTA implementation via shortcode
  - Integration with popular page builders
  - Maintain display logic and relationships
  - Example templates for different builders
  - Performance considerations
  - Documentation for both modes
- [ ] Plausible Analytics integration
  - Click tracking
  - Event naming
  - Performance optimization
- [ ] Style management improvements
  - Inline stylesheet generation
  - Conditional loading
  - Mobile optimization
- [ ] Future background control improvements
  - Separate background controls for main container and content container
  - Individual color pickers for each container
  - Gradient background options
  - Background image opacity control
  - Background pattern library
  - Hover state background effects
  - Background animation options
  - Mobile-specific background settings

## Plugin Architecture
```
nova-ctas/
├── admin/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── class-nova-cta-manager.php
│   ├── class-nova-taxonomy.php
│   ├── class-nova-related-posts.php
│   ├── class-nova-related-posts-widget.php
│   └── class-nova-shortcode.php
├── public/
│   └── css/
│       └── frontend.css
├── languages/
├── nova-ctas.php
└── README.md
```

## Version History
- 1.1.19: Fixed CTA display and array index error
- 1.1.18: Improved content processing and error handling
- 1.1.17: Enhanced relationship logic
- 1.1.16: Fixed front-end display issues
- 1.1.15: Improved CTA HTML structure
- 1.1.14: Added error logging and validation
- 1.1.13: Enhanced settings management
- 1.1.12: Fixed form submission handling
- 1.1.11: Updated nonce handling
- 1.1.10: Streamlined UI and removed duplicates
- 1.0.0: Initial release 