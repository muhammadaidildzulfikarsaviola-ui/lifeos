# LIFEOS UI/UX DESIGN SPECIFICATION

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Design Baseline  
**Design Direction:** Editorial, Minimal, Functional  
**Theme:** Light  
**Language:** Indonesian  
**Framework:** Bootstrap 5.3

---

# 1. DESIGN PHILOSOPHY

LIFEOS menggunakan pendekatan editorial interface yang mengutamakan keterbacaan, hierarki informasi, dan efisiensi penggunaan.

Tampilan harus terasa seperti personal workspace yang matang, bukan dashboard template generik.

## Design Principles

1. Clarity over decoration.
2. Consistency over novelty.
3. Information hierarchy over visual noise.
4. Functional interactions over decorative animations.
5. Comfortable density for long sessions.
6. Responsive behavior across devices.
7. Minimal dependencies.
8. No AI-generated visual gimmicks.

## Visual Restrictions

Dilarang menggunakan:

- Gradient backgrounds.
- Glassmorphism.
- Excessive shadows.
- Excessive rounded cards.
- Excessive decorative icons.
- Unnecessary animations.
- Randomly varying typography.
- Decorative charts without useful information.
- Excessive colored badges.

---

# 2. COLOR SYSTEM

LIFEOS menggunakan light theme dengan warna netral sebagai fondasi.

## Core Palette

| Token | Color | Usage |
|---|---|---|
| Background | #F7F7F5 | Main application background |
| Surface | #FFFFFF | Cards and panels |
| Surface Secondary | #F0F0ED | Secondary sections |
| Text Primary | #242424 | Main text |
| Text Secondary | #737373 | Supporting text |
| Border | #E5E5E0 | Dividers and borders |
| Primary | #315C4C | Main actions |
| Primary Hover | #264A3D | Hover state |
| Success | #357A55 | Success feedback |
| Warning | #B7791F | Warning feedback |
| Danger | #B84040 | Destructive actions |
| Info | #3B6F91 | Informational feedback |

Colors must be implemented through CSS variables.

Example:

```css
:root {
    --color-background: #F7F7F5;
    --color-surface: #FFFFFF;
    --color-surface-secondary: #F0F0ED;

    --color-text-primary: #242424;
    --color-text-secondary: #737373;

    --color-border: #E5E5E0;

    --color-primary: #315C4C;
    --color-primary-hover: #264A3D;

    --color-success: #357A55;
    --color-warning: #B7791F;
    --color-danger: #B84040;
    --color-info: #3B6F91;
}
```

Color usage must remain consistent across all modules.

Do not use color as the only indicator of status. Include text labels or icons where appropriate.

---

# 3. TYPOGRAPHY

## Typography Principles

- One primary font family.
- Consistent font sizing.
- Clear hierarchy.
- Comfortable line height.
- No excessive font weight variation.

Recommended default:

```css
:root {
    --font-family-base:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}
```

If Inter is not available locally, use system fallback.

## Font Scale

| Token | Size | Usage |
|---|---:|---|
| XS | 12px | Metadata |
| SM | 13px | Supporting text |
| BASE | 14px | Main UI text |
| MD | 16px | Emphasized body text |
| LG | 18px | Section headings |
| XL | 22px | Page headings |
| 2XL | 28px | Dashboard highlights |
| 3XL | 34px | Large summary values |

## Font Weight

- 400: Regular.
- 500: Medium.
- 600: Semibold.
- 700: Bold.

Avoid using bold typography everywhere.

---

# 4. SPACING SYSTEM

Use a consistent 4px-based spacing system.

| Token | Value |
|---|---:|
| XS | 4px |
| SM | 8px |
| MD | 12px |
| LG | 16px |
| XL | 20px |
| 2XL | 24px |
| 3XL | 32px |
| 4XL | 40px |
| 5XL | 48px |

Rules:

- Form fields should have comfortable vertical spacing.
- Related elements should be grouped.
- Sections should have clear separation.
- Avoid excessive whitespace that hides useful information.
- Avoid excessively dense layouts.

---

# 5. LAYOUT ARCHITECTURE

## Main Application Layout

The application uses hybrid navigation:

- Sidebar for primary module navigation.
- Topbar for global controls.
- Main content area for module pages.

Desktop structure:

```text
+----------------------------------------------------------+
| Sidebar       | Topbar                                   |
|               |------------------------------------------|
| LIFEOS        |                                          |
|               | Main Content                             |
| Dashboard     |                                          |
| Finance       | Page Heading                             |
| Schedule      | Page Description                         |
| Tasks         |                                          |
| Habits        | Content                                  |
| Journal       |                                          |
| Notes         |                                          |
| Goals         |                                          |
| Projects      |                                          |
| Reports       |                                          |
|               |                                          |
| Settings      |                                          |
+----------------------------------------------------------+
```

## Sidebar

Requirements:

- Collapsible.
- Expanded by default on desktop.
- Automatically adapts to viewport width.
- Compact icon mode when collapsed.
- Active navigation indicator.
- Group related navigation items.
- Settings placed separately from primary modules.

Recommended dimensions:

- Expanded: 240px.
- Collapsed: 68px.
- Transition: 180ms.

## Topbar

Contains:

- Current page context.
- Global search.
- Quick create action.
- Notification access.
- Profile/settings access.

Do not overload the topbar with controls already available inside module pages.

## Main Content

Recommended maximum content width:

- Standard pages: 1440px.
- Reading-oriented pages: 1000px.
- Full-width pages: calendar, Kanban, and data tables.

Page padding:

- Desktop: 28px.
- Tablet: 20px.
- Mobile: 16px.

---

# 6. RESPONSIVE BEHAVIOR

## Desktop

- Sidebar visible.
- Full navigation.
- Multi-column dashboard.
- Full calendar views.
- Full data tables.

## Tablet

- Sidebar may collapse.
- Dashboard columns reduce.
- Forms adapt to narrower widths.
- Tables may use horizontal scrolling where necessary.

## Mobile

- Sidebar becomes off-canvas navigation.
- Topbar remains compact.
- Dashboard becomes single-column.
- Forms become vertically stacked.
- Tables use responsive cards or controlled horizontal scrolling.
- Primary actions remain accessible.

Never hide critical functionality solely because the viewport is small.

---

# 7. COMPONENT SYSTEM

All modules should reuse shared components.

## Buttons

Variants:

- Primary.
- Secondary.
- Outline.
- Ghost.
- Danger.

Sizes:

- Small.
- Medium.
- Large.

Rules:

- One primary action per major section where practical.
- Destructive actions must be visually distinct.
- Icon-only buttons require accessible labels or tooltips.
- Disabled states must be clear.

## Cards

Card types:

- Summary card.
- Content card.
- Statistic card.
- Widget card.
- Interactive card.

Rules:

- Use borders and neutral backgrounds.
- Avoid excessive shadows.
- Use consistent padding.
- Do not nest cards unnecessarily.

## Forms

Supported elements:

- Text input.
- Number input.
- Date picker.
- Time picker.
- Select.
- Multi-select.
- Checkbox.
- Radio.
- Textarea.
- File upload.
- Rich text editor.

Form rules:

- Labels must always be visible.
- Required fields must be identifiable.
- Validation appears near the relevant field.
- Preserve valid input after validation errors.
- Show success feedback after saving.
- Confirm destructive changes.

## Tables

Requirements:

- Consistent column alignment.
- Sorting where useful.
- Search and filters.
- Pagination for large datasets.
- Clear empty states.
- Responsive behavior.

## Modals

Use for:

- Quick create.
- Confirmation.
- Small edits.
- Focused workflows.

Use full pages for complex forms.

## Toast Notifications

Types:

- Success.
- Error.
- Warning.
- Information.

Toast should communicate the result without interrupting the workflow.

---

# 8. GLOBAL INTERACTIONS

## Global Search

Search across:

- Tasks.
- Projects.
- Goals.
- Notes.
- Journal entries.
- Finance records.
- Calendar events.

Search results should show entity type and relevant context.

Search must respect the active profile.

## Quick Create

Provides shortcuts to create:

- Task.
- Schedule event.
- Transaction.
- Habit.
- Note.
- Journal entry.
- Project.
- Goal.

Quick create should use simplified forms and defer complex fields to the full module page.

## Keyboard Shortcuts

Initial common shortcuts:

| Shortcut | Action |
|---|---|
| Ctrl + K | Open global search |
| Ctrl + N | Quick create |
| Ctrl + S | Save current form or editor |
| Escape | Close modal or popup |
| / | Focus search when not editing a field |

Shortcuts must not override normal browser behavior in text inputs.

## Undo

Undo is available for supported recent actions.

Undo must:

- Identify the affected record.
- Validate that restoration is still safe.
- Avoid overwriting newer changes.
- Provide clear feedback.

---

# 9. DASHBOARD DESIGN

Dashboard is an overview, not a replacement for individual modules.

## Widget Layout

Suggested desktop grid:

- Finance Summary: 2 columns.
- Today's Schedule: 2 columns.
- Upcoming Tasks: 2 columns.
- Habit Progress: 1 column.
- Project Progress: 1 column.
- Quick Notes: 1 column.
- Goals Overview: 1 column.
- Recent Journal Entries: 1 column.
- Upcoming Bills & Installments: 2 columns.
- Monthly Analytics: 2 columns.

The actual grid may adapt according to available screen width.

## Widget Rules

- Show concise information.
- Provide links to module details.
- Avoid duplicate data entry.
- Support empty states.
- Use consistent card hierarchy.
- Avoid excessive charts on the dashboard.

Dashboard data must be retrieved from module services.

---

# 10. MODULE UI SPECIFICATIONS

## Finance

Primary views:

- Finance overview.
- Transactions.
- Accounts.
- Budgets.
- Loans.
- Fixed bill reminders.

Prioritize readable financial values and clear transaction direction.

Use tabular number formatting.

## Schedule

Primary views:

- Month.
- Week.
- Day.
- Agenda.

Events must be distinguishable by type without relying exclusively on colors.

Drag and drop must provide visual feedback.

## Tasks

Primary views:

- Task list.
- Kanban.
- Task detail.
- Nested subtasks.

Task status and priority must be immediately visible.

## Habits

Primary views:

- Daily tracker.
- Weekly tracker.
- Habit detail.
- Statistics.

Completion and rest day must be visually distinguishable.

## Journal

Primary views:

- Journal list.
- Calendar.
- Editor.
- Mood history.

Reading and writing comfort take priority over dense controls.

## Notes

Primary views:

- Folder navigation.
- Notes list.
- Note editor.

Support pinned and archived states.

## Goals

Primary views:

- Goal overview.
- Goal detail.
- Milestones.

Show progress method and target clearly.

## Projects

Primary views:

- Project list.
- Project detail.
- Timeline.
- Milestones.
- Budget.

Project progress must be linked to actual task data.

## Reports

Primary views:

- Summary.
- Financial reports.
- Productivity reports.
- Custom period.

Charts must always have meaningful labels and values.

---

# 11. EDITOR REQUIREMENTS

Journal and Notes support Markdown and rich text.

Requirements:

- Manual save.
- Clear editing state.
- Unsaved changes indicator.
- Preview where applicable.
- Consistent typography.
- Keyboard accessibility.

Do not autosave silently because manual save is the selected requirement.

Warn the user before leaving a page with unsaved changes.

---

# 12. NOTIFICATION EXPERIENCE

Supported notification types:

- In-app.
- Browser notifications.

Notification behavior:

- User permission is required for browser notifications.
- Browser notifications must have an in-app fallback.
- Notifications must respect configured reminders.
- Cancelled events must not generate reminders.
- Duplicate notifications must be prevented.

Browser support must be tested in Chrome, Edge, and Brave.

---

# 13. EMPTY, LOADING, AND ERROR STATES

Every major module must define:

## Empty State

- Explain what the page contains.
- Explain why it is currently empty.
- Provide a relevant create action.

## Loading State

- Use subtle loading indicators.
- Avoid unnecessary full-screen loading overlays.
- Prevent duplicate form submissions.

## Error State

- Explain the problem in plain language.
- Preserve valid form data where possible.
- Provide recovery guidance.

## Success State

- Confirm the action.
- Update relevant content.
- Avoid unnecessary page reloads.

---

# 14. ACCESSIBILITY

Requirements:

- Keyboard navigation.
- Visible focus indicators.
- Semantic HTML.
- Accessible form labels.
- Sufficient color contrast.
- Screen-reader friendly controls.
- No color-only status communication.
- Modal focus management.

---

# 15. PERFORMANCE RULES

- Load module-specific JavaScript only when required.
- Avoid unnecessary chart rendering.
- Use pagination for large data lists.
- Lazy-load attachments and heavy content.
- Avoid unnecessary DOM updates.
- Keep transitions short.
- Avoid large animation libraries.
- Avoid loading all vendor assets on every page.

---

# 16. DESIGN ACCEPTANCE CRITERIA

The UI/UX baseline is accepted when:

1. All modules use consistent design tokens.
2. Typography is consistent.
3. Layout is responsive.
4. Sidebar and topbar behave correctly.
5. All buttons have functional actions.
6. Forms provide validation feedback.
7. Empty, loading, error, and success states exist.
8. Keyboard navigation works for major interactions.
9. No gradient or glassmorphism is used.
10. Dashboard widgets display actual module data.
11. No critical browser console errors exist.
12. UI remains comfortable during long usage sessions.

---

# END OF UI/UX SPECIFICATION