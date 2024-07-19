<table class="table payment-method-item">
    <tbody>
    <tr class="border-pay-row">
        <td class="border-pay-col">
            <i class="fa fa-theme-payments"></i>
        </td>
        <td style="width: 20%">
            <img class="filter-black" src="{{ asset('vendor/core/plugins/flutter-wave/images/flutter-wave.png') }}" alt="{{ $name }}">
        </td>
        <td class="border-right">
            <ul>
                <li>
                    <a href="https://flutterwave.com" target="_blank">{{ $name }}</a>
                    <p>{{ trans('plugins/flutter-wave::flutter-wave.description') }}</p>
                </li>
            </ul>
        </td>
    </tr>
    <tr class="bg-white">
        <td colspan="3">
            <div class="float-start" style="margin-top: 5px;">
                <div @class(['payment-name-label-group', 'hidden' => ! $status])>
                    <span class="payment-note v-a-t">{{ trans('plugins/payment::payment.use') }}:</span>
                    <label class="ws-nm inline-display method-name-label">{{ get_payment_setting('name', $moduleName) }}</label>
                </div>
            </div>
            <div class="float-end">
                <a @class(['btn btn-secondary toggle-payment-item edit-payment-item-btn-trigger', 'hidden' => ! $status])>{{ trans('plugins/payment::payment.edit') }}</a>
                <a @class(['btn btn-secondary toggle-payment-item save-payment-item-btn-trigger', 'hidden' => $status])>{{ trans('plugins/payment::payment.settings') }}</a>
            </div>
        </td>
    </tr>
    <tr class="paypal-online-payment payment-content-item hidden">
        <td class="border-left" colspan="3">
            <form>
                <input type="hidden" name="type" value="{{ $moduleName }}" class="payment_type">

                <div class="row">
                    <div class="col-sm-6">
                        <ul>
                            <li>
                                <label>{{ trans('plugins/payment::payment.configuration_instruction', ['name' => $name]) }}</label>
                            </li>
                            <li class="payment-note">
                                <p>{{ trans('plugins/payment::payment.configuration_requirement', ['name' => $name]) }}:</p>
                                <ul class="m-md-l" style="list-style-type:decimal">
                                    <li style="list-style-type:decimal">
                                        <a href="https://app.flutterwave.com/register" target="_blank">
                                            {{ trans('plugins/payment::payment.service_registration', ['name' => $name]) }}
                                        </a>
                                    </li>
                                    <li style="list-style-type:decimal">
                                        <p>{{ trans('plugins/payment::payment.stripe_after_service_registration_msg', ['name' => $name]) }}</p>
                                    </li>
                                    <li style="list-style-type:decimal">
                                        <p>{{ trans('plugins/payment::payment.stripe_enter_client_id_and_secret') }}</p>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="col-sm-6">
                        <div class="well bg-white">
                            <x-core-setting::text-input
                                name="payment_flutter_wave_name"
                                :label="trans('plugins/payment::payment.method_name')"
                                :value="get_payment_setting('name', $moduleName, trans('plugins/payment::payment.pay_online_via', ['name' => $name]))"
                                data-counter="400"
                            />

                            <x-core-setting::form-group>
                                <label class="text-title-field" for="payment_flutter_wave_description">{{ trans('core/base::forms.description') }}</label>
                                <textarea class="next-input" name="payment_flutter_wave_description" id="payment_flutter_wave_description">{{ get_payment_setting('description', $moduleName, __('Payment with flutter_wave')) }}</textarea>
                            </x-core-setting::form-group>

                            <x-core-setting::text-input
                                :name="'payment_' . $moduleName . '_public_key'"
                                :label="trans('plugins/flutter-wave::flutter-wave.public_key')"
                                :value="get_payment_setting('public_key', $moduleName)"
                                placeholder="xxxxxx"
                            />

                            <x-core-setting::text-input
                                :name="'payment_' . $moduleName . '_secret_key'"
                                :label="trans('plugins/flutter-wave::flutter-wave.secret_key')"
                                :value="get_payment_setting('secret_key', $moduleName)"
                                type="password"
                                placeholder="xxxxxxxx"
                            />

                            <x-core-setting::text-input
                                :name="'payment_' . $moduleName . '_encryption_key'"
                                :label="trans('plugins/flutter-wave::flutter-wave.encryption_key')"
                                :value="get_payment_setting('encryption_key', $moduleName)"
                                type="password"
                                placeholder="xxxxxxxx"
                            />

                            {!! apply_filters(PAYMENT_METHOD_SETTINGS_CONTENT, null, $moduleName) !!}
                        </div>
                    </div>
                </div>

                <div class="col-12 bg-white text-end">
                    <button @class(['btn btn-warning disable-payment-item', 'hidden' => ! $status]) type="button">{{ trans('plugins/payment::payment.deactivate') }}</button>
                    <button @class(['btn btn-info save-payment-item btn-text-trigger-save', 'hidden' => $status]) type="button">{{ trans('plugins/payment::payment.activate') }}</button>
                    <button @class(['btn btn-info save-payment-item btn-text-trigger-update', 'hidden' => ! $status]) type="button">{{ trans('plugins/payment::payment.update') }}</button>
                </div>
            </form>
        </td>
    </tr>
    </tbody>
</table>
