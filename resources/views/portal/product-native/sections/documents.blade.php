@if($perm('documents'))
@php($canEditDocuments = $perm('documents', 'edit'))
<div class="ft-product-documents-workspace ft-ux-documents-workspace">
    <div class="ft-ux-section-intro ft-ux-documents-intro">
        <p>Keep the product certificate and template together. Each upload is saved individually through FlowTracker.</p>
        @unless($canEditDocuments)<span class="ft-ux-readonly-pill">View only</span>@endunless
    </div>
    <div class="ft-ux-documents-grid">
        <div class="ft-ux-certificate-reference">
            <span class="ft-ux-doc-label">Test certificate number <em>Optional</em></span>
            <div class="ft-product-locked-field">{{ ($product['test_certificate_number'] ?? '') ?: '—' }}</div>
            <small>Reference number printed on the test certificate.</small>
        </div>
        @foreach(['certificate'=>'Certificate & Test Report','template'=>'Product template'] as $kind => $label)
            @php($asset = $assets->firstWhere('kind', $kind))
            <article class="ft-ux-document-card">
                <div class="ft-ux-document-card-heading">
                    <span class="ft-ux-document-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/></svg>
                    </span>
                    <div><strong>{{ $label }}</strong><small>Optional · PDF, DOCX, PNG or JPG · Max 5 MB</small></div>
                </div>
                @if($asset)
                    <div class="ft-ux-document-current">
                        <div class="ft-ux-document-filename"><strong title="{{ $asset['label'] ?? 'Current file' }}">{{ $asset['label'] ?? 'Current file' }}</strong><small>Current document</small></div>
                        <a class="ft-product-page-btn is-secondary ft-ux-document-view" href="{{ route('supplier.products.module.asset', ['product'=>$recordId,'kind'=>$kind]) }}">Download</a>
                    </div>
                @else
                    <div class="ft-ux-document-current is-empty"><span>No file uploaded yet</span></div>
                @endif
                @if($canEditDocuments)
                    <form class="ft-ux-document-upload" data-native-document-upload method="POST" enctype="multipart/form-data" action="{{ route('supplier.products.module.action') }}">
                        @csrf
                        <input type="hidden" name="operation" value="upload_asset">
                        <input type="hidden" name="group" value="product">
                        <input type="hidden" name="id" value="{{ $recordId }}">
                        <input type="hidden" name="kind" value="{{ $kind }}">
                        <label class="ft-ux-file-picker" data-native-document-drop>
                            <input type="file" name="file" required accept=".pdf,.docx,.png,.jpg,.jpeg,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/png,image/jpeg">
                            <span class="ft-ux-file-picker-main"><strong>Choose file</strong> or drop it here</span>
                            <small data-native-file-name aria-live="polite">No file selected</small>
                        </label>
                        <div class="ft-ux-document-actions">
                            <small>{{ $asset ? 'Upload a file to replace the current document.' : 'Select a file to upload.' }}</small>
                            <button type="submit" data-native-file-submit class="ft-product-page-btn is-primary">{{ $asset ? 'Replace file' : 'Upload file' }}</button>
                        </div>
                    </form>
                @endif
            </article>
        @endforeach
    </div>
    @unless($canEditDocuments)
        <p class="ft-ux-permission-note">Document uploads are read-only for your supplier account. Uploading requires Documents → Edit permission in FlowTracker.</p>
    @endunless
    <p class="ft-product-document-note">ⓘ These documents stay linked to this product and are available when it is added to an Inquiry or Order.</p>
</div>
@endif
