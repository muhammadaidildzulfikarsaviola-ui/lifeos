# LIFEOS FRONTEND COMPONENT SPECIFICATION

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Frontend Component Specification  
**Framework:** Bootstrap 5.3  
**JavaScript:** Vanilla JS + Alpine.js  
**Calendar:** FullCalendar  
**Drag & Drop:** SortableJS  
**Charts:** Chart.js

---

# 1. PURPOSE

Dokumen ini mendefinisikan komponen frontend reusable LIFEOS.

Tujuan:

- Menjaga konsistensi visual.
- Mengurangi duplikasi UI.
- Menstandarkan interaksi.
- Memastikan accessibility.
- Mempermudah pengembangan sembilan modul.
- Memisahkan komponen UI dari business logic.

Komponen reusable harus memiliki API, state, dan perilaku yang jelas.

---

# 2. DESIGN PRINCIPLES

## 2.1 Visual Direction

LIFEOS menggunakan pendekatan editorial productivity dashboard.

Karakter visual:

- Clean.
- Minimal.
- Functional.
- Comfortable density.
- Strong typography hierarchy.
- Clear information grouping.
- Subtle borders.
- Consistent spacing.

Hindari:

- Gradient berlebihan.
- Glassmorphism.
- Shadow berlebihan.
- Animasi dekoratif yang tidak diperlukan.
- Terlalu banyak warna aksen.
- Card bersarang tanpa alasan.

## 2.2 Interaction Principles

- Setiap tombol harus memiliki fungsi.
- Setiap input harus memiliki label.
- Setiap modal harus dapat ditutup dengan jelas.
- Destructive actions memerlukan konfirmasi.
- Loading state harus terlihat.
- Error state harus dapat dipahami.
- Empty state harus memberikan konteks.
- Feedback harus muncul setelah operasi berhasil.

---

# 3. DESIGN TOKENS

Design tokens menjadi sumber nilai visual utama.

## 3.1 Color Tokens

Baseline rekomendasi:

| Token | Purpose |
|---|---|
| primary | Main action |
| secondary | Secondary action |
| background | Application background |
| surface | Card and panel |
| surface-muted | Secondary surface |
| text-primary | Main text |
| text-secondary | Supporting text |
| border | Dividers and borders |
| success | Successful state |
| warning | Warning state |
| danger | Destructive or error state |
| info | Informational state |

Semua token harus didefinisikan sebagai CSS custom properties.

Contoh:

```css
:root {
    --color-primary: #315C4C;
    --color-background: #F7F8F6;
    --color-surface: #FFFFFF;
    --color-text-primary: #202522;
    --color-text-secondary: #707873;
    --color-border: #E4E8E4;

    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;

    --spacing-1: 4px;
    --spacing-2: 8px;
    --spacing-3: 12px;
    --spacing-4: 16px;
    --spacing-5: 24px;
    --spacing-6: 32px;
}
```

Warna di atas adalah baseline desain dan dapat disesuaikan melalui design review.

Jangan menyebarkan nilai warna hardcoded ke seluruh komponen.

## 3.2 Typography

Hierarchy:

| Token | Usage |
|---|---|
| display | Dashboard highlights |
| heading-1 | Page title |
| heading-2 | Section title |
| heading-3 | Card title |
| body | Main content |
| body-small | Supporting text |
| caption | Metadata |
| label | Form labels |

Gunakan font system stack atau font lokal yang telah dipilih.

Jangan mewajibkan koneksi internet untuk memuat font.

## 3.3 Spacing

Gunakan spacing scale yang konsisten.

Nilai spacing utama:

- 4px.
- 8px.
- 12px.
- 16px.
- 24px.
- 32px.
- 48px.

Hindari margin dan padding acak tanpa alasan layout.

---

# 4. APPLICATION SHELL

Application shell terdiri dari:

- Sidebar.
- Topbar.
- Main content.
- Global search.
- Notification area.
- Quick action.
- Breadcrumb.
- Responsive navigation.

## 4.1 Sidebar

Menu utama:

1. Dashboard.
2. Finance.
3. Schedule.
4. Tasks.
5. Habits.
6. Journal.
7. Notes.
8. Goals.
9. Projects.
10. Reports.
11. Settings.

Behavior:

- Active navigation indicator.
- Collapsible.
- Tooltip ketika collapsed.
- Mobile drawer.
- Persistent navigation state.

## 4.2 Topbar

Komponen:

- Page title.
- Breadcrumb.
- Global search.
- Notification trigger.
- Quick action.
- Profile context.

Topbar tidak boleh terlalu padat.

## 4.3 Main Content

Aturan:

- Consistent page padding.
- Responsive width.
- Page title hierarchy.
- Consistent section spacing.
- Avoid nested scrolling where possible.

---

# 5. BUTTON COMPONENTS

Variant:

- Primary.
- Secondary.
- Outline.
- Ghost.
- Danger.
- Link.

Sizes:

- Small.
- Medium.
- Large.

States:

- Default.
- Hover.
- Focus.
- Active.
- Disabled.
- Loading.

Rules:

- Primary digunakan untuk aksi utama.
- Danger digunakan untuk aksi destruktif.
- Disabled harus benar-benar tidak dapat digunakan.
- Loading harus mencegah double submission.
- Icon-only button harus memiliki accessible label.

Setiap button harus memiliki action yang jelas.

---

# 6. FORM COMPONENTS

Komponen:

- Text input.
- Textarea.
- Number input.
- Currency input.
- Date picker.
- Time picker.
- Date-time picker.
- Select.
- Multi-select.
- Checkbox.
- Radio.
- Toggle.
- File upload.
- Search input.

## Form Rules

- Label wajib.
- Required field ditandai.
- Validation error ditampilkan dekat field.
- Helper text bersifat opsional.
- Format input mengikuti jenis data.
- Submit button memiliki loading state.
- Form tidak boleh kehilangan input tanpa peringatan jika penyimpanan gagal.

## Currency Input

- Currency v1 IDR.
- Tampilan menggunakan format Rupiah.
- Backend menerima numeric decimal.
- Pemisah ribuan dan desimal harus dinormalisasi sebelum dikirim.

## Date Input

- Tanggal mengikuti timezone aplikasi.
- Tampilan tanggal menggunakan format Indonesia.
- API mengirim format yang ditentukan API Contract.

---

# 7. MODAL COMPONENT

Modal digunakan untuk:

- Create.
- Edit.
- Detail.
- Confirmation.
- Preview.
- Import.
- Restore.

Rules:

- Modal memiliki title.
- Modal memiliki close action.
- Escape menutup modal kecuali sedang dalam proses yang tidak boleh dibatalkan.
- Background interaction dinonaktifkan saat modal aktif.
- Focus berada di dalam modal.
- Focus dikembalikan ke pemicu setelah modal ditutup.
- Modal destruktif harus menampilkan konsekuensi tindakan.

Modal tidak boleh menampung terlalu banyak form field.

Gunakan halaman detail jika proses terlalu kompleks.

---

# 8. TOAST & NOTIFICATION

Toast variants:

- Success.
- Error.
- Warning.
- Info.

Behavior:

- Muncul setelah action.
- Dapat ditutup manual.
- Auto-dismiss untuk pesan non-kritis.
- Error penting tidak boleh hilang terlalu cepat.
- Jangan menampilkan pesan sensitif.

Toast bukan pengganti inline validation.

Notification center memiliki daftar notifikasi dan status read/unread.

---

# 9. DATA TABLE

Data table digunakan pada:

- Transactions.
- Tasks.
- Projects.
- Notes.
- Import preview.
- History.

Features:

- Sorting.
- Filtering.
- Pagination.
- Search.
- Row actions.
- Empty state.
- Loading state.
- Responsive layout.

Rules:

- Pagination dilakukan melalui backend.
- Sorting menggunakan whitelist.
- Table tidak boleh memuat seluruh dataset besar.
- Row action memiliki accessible label.
- Destructive action memerlukan confirmation.

Pada mobile, gunakan responsive table atau list representation sesuai kebutuhan konten.

---

# 10. CARD COMPONENTS

Card digunakan untuk mengelompokkan informasi.

Types:

- Summary card.
- Dashboard widget.
- Content card.
- Statistics card.
- Preview card.

Rules:

- Card memiliki hierarchy yang jelas.
- Hindari card bersarang tanpa kebutuhan.
- Hindari shadow berlebihan.
- Card interaktif harus memiliki affordance yang jelas.
- Card tidak boleh terlihat clickable jika tidak memiliki action.

---

# 11. EMPTY, LOADING & ERROR STATES

Semua komponen data harus mendukung:

## Empty State

Menjelaskan:

- Belum ada data.
- Mengapa data kosong jika relevan.
- Action untuk membuat data pertama.

## Loading State

- Skeleton atau spinner.
- Tidak menyebabkan layout melompat berlebihan.
- Hindari loading animation yang terlalu lama.

## Error State

- Pesan yang mudah dipahami.
- Retry jika memungkinkan.
- Jangan menampilkan stack trace.
- Jangan menghapus input pengguna ketika request gagal.

---

# 12. SEARCH & FILTER COMPONENTS

Global search:

- Search tasks.
- Projects.
- Notes.
- Journal.
- Transactions.
- Goals.

Search behavior:

- Debounce input.
- Minimum query length sesuai endpoint.
- Loading state.
- Empty result.
- Keyboard navigation.
- Escape untuk menutup.
- Enter untuk membuka hasil terpilih.

Filter component:

- Status.
- Priority.
- Date range.
- Category.
- Project.
- Tags.

Filter state harus dapat di-reset.

---

# 13. CALENDAR COMPONENTS

Menggunakan FullCalendar.

Views:

- Month.
- Week.
- Day.
- List/Agenda.

Features:

- Event creation.
- Event editing.
- Drag and drop.
- Recurring events.
- Occurrence override.
- Shift display.
- Task schedule display.
- Holiday display.

Calendar UI hanya menampilkan data dari service.

Jangan membuat aturan recurrence sendiri di frontend.

---

# 14. KANBAN COMPONENT

Digunakan pada task management.

Columns:

- Not started.
- In progress.
- On hold.
- Completed.
- Cancelled.

Features:

- Drag and drop.
- Status update.
- Priority indicator.
- Deadline.
- Subtask indicator.
- Task detail.

Setiap perubahan status harus dikonfirmasi backend.

Optimistic UI hanya boleh digunakan jika kegagalan request dapat dikembalikan ke state sebelumnya.

---

# 15. CHART COMPONENTS

Menggunakan Chart.js.

Types:

- Line.
- Bar.
- Doughnut.
- Pie.
- Area.

Digunakan pada:

- Finance.
- Reports.
- Dashboard.
- Habits.
- Projects.
- Goals.

Rules:

- Data berasal dari backend aggregation.
- Chart tidak menjadi sumber data utama.
- Chart harus memiliki accessible summary.
- Tooltip harus jelas.
- Empty dataset ditangani.
- Format nominal menggunakan Rupiah.

---

# 16. RICH TEXT EDITOR

Digunakan pada:

- Journal.
- Notes.
- Project descriptions.
- Task descriptions.

Features:

- Bold.
- Italic.
- Underline.
- Headings.
- Lists.
- Links.
- Quotes.
- Code blocks jika diperlukan.

Rules:

- HTML output harus disanitasi.
- Unsupported markup ditangani dengan aman.
- Autosave tidak aktif secara default.
- Manual save menjadi perilaku utama.
- Unsaved changes warning wajib.

---

# 17. FILE UPLOAD COMPONENT

Features:

- Drag and drop.
- File picker.
- Upload progress.
- File size indicator.
- File type validation.
- Remove attachment.
- Upload error.

Batas:

50 MB per file.

Format:

- JPG.
- JPEG.
- PNG.
- WEBP.
- PDF.
- DOCX.
- XLSX.
- TXT.
- ZIP.

Frontend validation hanya membantu pengguna.

Backend tetap melakukan validasi MIME, extension, dan ukuran.

---

# 18. DRAG & DROP

Menggunakan SortableJS.

Use cases:

- Kanban.
- Checklist ordering.
- Notes ordering.
- Dashboard widget ordering.
- Folder ordering.

Rules:

- Drag handle harus jelas.
- Keyboard alternative disediakan untuk operasi penting.
- Backend menyimpan urutan final.
- Failed request harus mengembalikan urutan sebelumnya.
- Jangan mengaktifkan drag pada seluruh card jika card juga memiliki interaksi lain.

---

# 19. KEYBOARD SHORTCUTS

Baseline:

| Shortcut | Action |
|---|---|
| Ctrl + K | Global search |
| Ctrl + N | New item sesuai halaman |
| Ctrl + S | Save current form |
| Escape | Close modal or overlay |
| / | Focus search jika tidak sedang mengetik |

Shortcut tidak boleh mengganggu input field atau shortcut browser yang penting.

Tampilkan shortcut hints pada menu bantuan.

---

# 20. ACCESSIBILITY

Baseline:

- Semantic HTML.
- Proper labels.
- Keyboard navigation.
- Visible focus.
- Sufficient contrast.
- Accessible modal.
- Accessible form errors.
- Alt text untuk gambar bermakna.
- Screen reader-friendly status.

Target:

WCAG 2.2 AA sebagai arah evaluasi, dengan prioritas pada interaksi utama.

Jangan menggunakan warna sebagai satu-satunya indikator status.

---

# 21. RESPONSIVE BEHAVIOR

## Desktop

- Full sidebar.
- Multi-column dashboard.
- Full data tables.
- Full calendar.

## Tablet

- Collapsible sidebar.
- Reduced dashboard columns.
- Adaptive table layout.

## Mobile

- Navigation drawer.
- Single-column layout.
- Touch-friendly controls.
- Responsive modal.
- List alternative untuk tabel kompleks.
- Simplified calendar navigation.

Tidak boleh ada horizontal overflow pada viewport target.

---

# 22. COMPONENT FILE STRUCTURE

Recommended:

```text
resources/
├── views/
│   ├── layouts/
│   ├── components/
│   ├── dashboard/
│   ├── finance/
│   ├── schedule/
│   ├── tasks/
│   ├── habits/
│   ├── journal/
│   ├── notes/
│   ├── goals/
│   ├── projects/
│   └── reports/
│
└── styles/
    ├── tokens.css
    ├── base.css
    ├── layout.css
    ├── components.css
    └── modules/
```

JavaScript:

```text
public/assets/js/
├── core/
│   ├── api.js
│   ├── events.js
│   ├── modal.js
│   ├── toast.js
│   └── validation.js
│
├── components/
│   ├── data-table.js
│   ├── search.js
│   ├── form.js
│   ├── calendar.js
│   └── kanban.js
│
└── modules/
```

Reusable components tidak boleh menyimpan business logic modul tertentu.

---

# 23. COMPONENT TESTING

Setiap komponen penting harus diuji:

- Rendering.
- Interaction.
- Keyboard behavior.
- Loading state.
- Error state.
- Empty state.
- Responsive behavior.
- Accessibility.
- API failure handling.

Komponen shared harus diuji ulang ketika mengalami perubahan.

---

# 24. ACCEPTANCE CRITERIA

Frontend component system siap apabila:

1. Semua komponen memiliki visual tokens.
2. Semua komponen penting memiliki state lengkap.
3. Form validation konsisten.
4. Modal memiliki focus management.
5. Toast tidak menggantikan validation.
6. Data table menggunakan backend pagination.
7. Search dan filter konsisten.
8. Calendar menggunakan backend rules.
9. Rich text disanitasi.
10. Upload memiliki validasi backend.
11. Drag and drop memiliki rollback.
12. Responsive behavior diuji.
13. Accessibility dasar terpenuhi.
14. Tidak ada tombol tanpa fungsi.

---

# END OF FRONTEND COMPONENT SPECIFICATION