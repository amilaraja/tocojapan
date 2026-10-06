<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Company, bank and wording printed on LC proforma invoices. */
class ProformaSettings extends Settings
{
    public string $company_name;

    public string $company_address;

    public string $company_tel;

    public ?string $company_fax;

    public string $company_email;

    public string $company_url;

    public ?string $corporate_number;

    public string $port_of_loading;

    /** Days the proforma stays valid (Expiry Date = Issue Date + this). */
    public int $validity_days;

    public string $beneficiary_account_no;

    public string $bank_name;

    public string $bank_branch;

    public string $bank_address;

    public string $bank_swift;

    public string $bank_charge;

    public string $payment_terms;

    public string $signatory;

    public static function group(): string
    {
        return 'proforma';
    }
}
