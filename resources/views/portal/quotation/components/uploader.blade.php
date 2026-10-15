{{-- Reusable quotation attachment widget. Only data-kind and file-category change between stages. --}}
@if($kind === 'product')
<div class="quote-upload-toolbar">
  <div class="quote-upload-guidance"><strong>Supporting product files</strong><span>Images, specifications, certifications or sketches. Choose a category before adding files.</span></div>
  <div class="upload-type-field"><label for="quoteDocumentType">File category</label><select id="quoteDocumentType"><option value="Product specification">Product specification</option><option value="Supporting document">Supporting file / image</option><option value="Formal quotation">Formal quotation</option></select></div>
</div>
@else
<div class="quote-upload-toolbar"><div class="quote-upload-guidance"><strong>Supporting pricing files</strong><span>Attach spreadsheets or commercial proposals. The prices entered in the form are still required.</span></div></div>
@endif
<div class="upload-dropzone quote-upload-dropzone" id="{{ $kind === 'product' ? 'productQuoteDropzone' : 'priceQuoteDropzone' }}" data-upload-kind="{{ $kind }}" role="button" tabindex="0" aria-label="{{ $kind === 'product' ? 'Add product files' : 'Add pricing files' }}">
  <div class="upload-dropzone-main">
    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 16V4M8 8l4-4 4 4"/><path d="M5 14a4 4 0 0 0 1 7h12a4 4 0 0 0 1-7 7 7 0 0 0-13.5-2"/></svg>
    <div><strong>Drag & drop files here</strong><small>PDF, Word, Excel, CSV, TXT, images or ZIP · 20 MB per file · up to 10 files total</small></div>
  </div>
  <div class="upload-controls"><button type="button" class="btn btn-outline-teal" id="{{ $kind === 'product' ? 'browseQuoteFiles' : 'browsePriceFiles' }}">Browse files</button></div>
  <input id="{{ $kind === 'product' ? 'quoteFileInput' : 'priceFileInput' }}" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif,.zip" class="hidden" aria-label="{{ $kind === 'product' ? 'Product documents and images' : 'Pricing attachments' }}">
</div>
<p class="upload-feedback" id="{{ $kind === 'product' ? 'productUploadFeedback' : 'priceUploadFeedback' }}" role="status" aria-live="polite"></p>
<div class="uploaded-files-title">{{ $kind === 'product' ? 'Product attachments' : 'Pricing attachments' }} (<span id="{{ $kind === 'product' ? 'uploadedFileCount' : 'priceUploadedFileCount' }}">0</span>)</div>
<div class="upload-table-wrap"><table class="upload-table"><thead><tr><th>Type</th><th>File name</th><th>Size</th><th>Status</th><th>Actions</th></tr></thead><tbody id="{{ $kind === 'product' ? 'quoteUploadRows' : 'priceUploadRows' }}"></tbody></table></div>
