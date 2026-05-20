{{-- Shared CSS for any public page rendering Quill-generated HTML.
     Quill writes formatting as CSS classes (ql-align-*, ql-indent-*, ql-size-*,
     ql-font-*), and the quill.snow.css stylesheet itself only loads in the
     admin editor — so we replicate the rules the frontend needs here. --}}
<style>
    .page-content .ql-align-center  { text-align: center; }
    .page-content .ql-align-right   { text-align: right; }
    .page-content .ql-align-justify { text-align: justify; }
    .page-content .ql-direction-rtl { direction: rtl; text-align: inherit; }

    .page-content .ql-size-small { font-size: 0.75em; }
    .page-content .ql-size-large { font-size: 1.5em; }
    .page-content .ql-size-huge  { font-size: 2.5em; }

    .page-content .ql-font-serif     { font-family: Georgia, 'Times New Roman', serif; }
    .page-content .ql-font-monospace { font-family: Monaco, 'Courier New', monospace; }

    /* Quill applies indents in 3em increments per level (1–8). */
    .page-content .ql-indent-1 { padding-left: 3em; }
    .page-content .ql-indent-2 { padding-left: 6em; }
    .page-content .ql-indent-3 { padding-left: 9em; }
    .page-content .ql-indent-4 { padding-left: 12em; }
    .page-content .ql-indent-5 { padding-left: 15em; }
    .page-content .ql-indent-6 { padding-left: 18em; }
    .page-content .ql-indent-7 { padding-left: 21em; }
    .page-content .ql-indent-8 { padding-left: 24em; }

    .page-content img        { max-width: 100%; height: auto; border-radius: 8px; margin: 1rem 0; }
    .page-content iframe     { max-width: 100%; border-radius: 8px; margin: 1rem 0; }
    .page-content blockquote { border-left: 4px solid #1e40af; padding-left: 1rem; color: #4b5563; font-style: italic; }
    .page-content pre        { background: #1f2937; color: #f9fafb; padding: 1rem; border-radius: 8px; overflow-x: auto; }
</style>
