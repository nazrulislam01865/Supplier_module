@if($perm('options'))
<div class="ft-product-options-workspace">
    @if($perm('options', 'edit'))
        <form method="POST" action="{{ route('supplier.products.module.action') }}">
            @csrf
            <input type="hidden" name="operation" value="save_pricing"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="section" value="options">
            <div class="ft-native-repeat ft-product-options-list" data-repeat="options">
                <div class="ft-native-repeat-items">
                    @foreach(($product['options'] ?? []) as $i => $option)
                        <div class="ft-native-repeat-row ft-product-option-row">
                            <input type="hidden" name="options[{{ $i }}][key]" value="{{ $option['key'] }}">
                            <label class="ft-product-field ft-product-option-label-field"><span>Label <i>*</i></span><input name="options[{{ $i }}][label]" value="{{ $option['label'] }}" required maxlength="140" placeholder="e.g. Red, Large, Cotton"></label>
                            <label class="ft-product-field ft-product-option-charge-field"><span>Extra charge <em>Optional</em></span><input name="options[{{ $i }}][extra_charge]" type="number" min="0" step="0.01" value="{{ $option['extra_charge'] }}" placeholder="0.00"></label>
                            <div class="ft-product-field ft-product-option-image-field"><span>Image <em>Optional</em></span><div class="ft-product-option-image-input">
                                @if($option['has_image'] ?? false)<img src="{{ route('supplier.products.module.asset', ['product'=>$recordId,'kind'=>'option_image','option_key'=>$option['key']]) }}" alt="{{ $option['label'] }}">@else<span class="ft-product-option-image-placeholder">No image</span>@endif
                            </div></div>
                            <button type="button" class="ft-product-option-remove" data-native-remove-row>Remove</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="ft-product-option-add" data-native-add-row>+ Add option</button>
                <template><div class="ft-native-repeat-row ft-product-option-row">
                    <label class="ft-product-field ft-product-option-label-field"><span>Label <i>*</i></span><input name="options[__INDEX__][label]" required maxlength="140" placeholder="e.g. Red, Large, Cotton"></label>
                    <label class="ft-product-field ft-product-option-charge-field"><span>Extra charge <em>Optional</em></span><input name="options[__INDEX__][extra_charge]" type="number" min="0" step="0.01" value="0" placeholder="0.00"></label>
                    <div class="ft-product-field ft-product-option-image-field"><span>Image <em>Optional</em></span><div class="ft-product-option-image-input"><span class="ft-product-option-image-placeholder">No image</span></div></div>
                    <button type="button" class="ft-product-option-remove" data-native-remove-row>Remove</button>
                </div></template>
            </div>
            <div class="ft-native-form-actions"><button class="ft-product-page-btn is-primary" type="submit">Save product options</button></div>
        </form>
        @foreach(($product['options'] ?? []) as $option)
            <div class="ft-native-option-image">
                <strong>{{ $option['label'] }} — Image</strong>
                <form method="POST" action="{{ route('supplier.products.module.action') }}" enctype="multipart/form-data">@csrf
                    <input type="hidden" name="operation" value="upload_asset"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="kind" value="option_image"><input type="hidden" name="option_key" value="{{ $option['key'] }}">
                    <input type="file" name="file" required accept="image/png,image/jpeg,image/webp">
                    <button type="submit" class="ft-product-page-btn is-secondary">Update option image</button>
                </form>
            </div>
        @endforeach
    @else
        <div class="ft-product-options-list">
            @forelse(($product['options'] ?? []) as $option)
                <div class="ft-product-option-detail-row"><strong>{{ $option['label'] }}</strong>
                    <div class="ft-product-option-detail-charge"><small>Extra charge</small><span>{{ number_format((float) ($option['extra_charge'] ?? 0), 2) }}</span></div>
                    <div class="ft-product-option-detail-image">@if($option['has_image'] ?? false)
                        <img src="{{ route('supplier.products.module.asset', ['product'=>$recordId,'kind'=>'option_image','option_key'=>$option['key']]) }}" alt="{{ $option['label'] }}">
                    @else<span>No image</span>@endif</div>
                </div>
            @empty
                <small>No product options added.</small>
            @endforelse
        </div>
    @endif
</div>
@endif
