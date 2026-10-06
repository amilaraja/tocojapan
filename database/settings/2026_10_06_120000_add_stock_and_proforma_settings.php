<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('stock.supplier_margin_percent', 0.0);
        $this->migrator->add('stock.supplier_margin_fixed_usd', 0.0);

        // Defaults copied from TOCO's existing invoice sheet ("SL Export").
        $this->migrator->add('proforma.company_name', 'TOCO INTERNATIONAL CO., LTD');
        $this->migrator->add('proforma.company_address', 'TOCHIGI-KEN, SANO-SHI, HORIGOME CHO 3400-1, JAPAN - 327-0843');
        $this->migrator->add('proforma.company_tel', '+81 283 85 7224');
        $this->migrator->add('proforma.company_fax', '+81 283 24 4569');
        $this->migrator->add('proforma.company_email', 'first@toco-int.com');
        $this->migrator->add('proforma.company_url', 'https://tocojapan.com/');
        $this->migrator->add('proforma.corporate_number', '8060002040436');
        $this->migrator->add('proforma.port_of_loading', 'Yokohama / Japan');
        $this->migrator->add('proforma.validity_days', 2);
        $this->migrator->add('proforma.beneficiary_account_no', '1803916');
        $this->migrator->add('proforma.bank_name', 'MIZUHO BANK');
        $this->migrator->add('proforma.bank_branch', 'TATEBAYASHI BRANCH (701)');
        $this->migrator->add('proforma.bank_address', 'GUNMA-KEN, TATEBAYASHI-SHI, HON-CHO, 2 CHOME 9-2-6, JAPAN (POST 374-0024)');
        $this->migrator->add('proforma.bank_swift', 'MHCBJPJT');
        $this->migrator->add('proforma.bank_charge', "Sender's Account");
        $this->migrator->add('proforma.payment_terms', '100% before the due date by T/T remittance');
        $this->migrator->add('proforma.signatory', 'Toco International Co., Ltd');
    }

    public function down(): void
    {
        foreach (['supplier_margin_percent', 'supplier_margin_fixed_usd'] as $k) {
            $this->migrator->delete("stock.{$k}");
        }
        foreach (['company_name', 'company_address', 'company_tel', 'company_fax', 'company_email', 'company_url',
            'corporate_number', 'port_of_loading', 'validity_days', 'beneficiary_account_no', 'bank_name', 'bank_branch',
            'bank_address', 'bank_swift', 'bank_charge', 'payment_terms', 'signatory'] as $k) {
            $this->migrator->delete("proforma.{$k}");
        }
    }
};
