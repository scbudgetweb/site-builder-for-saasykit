{{-- Reloads the preview tab opened from a block panel's Preview button (window.siteBuilderPreview) after each save. --}}
<div x-data x-on:site-builder-page-saved.window="if (window.siteBuilderPreview && ! window.siteBuilderPreview.closed) { window.siteBuilderPreview.location.reload() }" class="hidden"></div>
