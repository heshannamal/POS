<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\BillingRefund;

class BillingPdf
{
    /**
     * Lightweight, text-based, single-page PDF (PDF 1.4).
     * No third-party composer dependency or shell process.
     */
    public function invoice(BillingInvoice $invoice, bool $receipt = false): string
    {
        $tenant = $invoice->tenant;
        $rows = [
            $receipt ? 'PAYMENT RECEIPT' : 'SUBSCRIPTION INVOICE',
            'POS Cloud - Platform Billing',
            '',
            'Document: '.($receipt ? $invoice->receipt_number : $invoice->number),
            'Invoice: '.$invoice->number,
            'Business: '.$invoice->bill_to_name,
            'Address: '.($invoice->bill_to_address ?: '-'),
            'Currency: '.$invoice->currency,
            'Issued: '.$invoice->issued_at?->format('Y-m-d'),
            'Status: '.strtoupper($invoice->status),
            '',
            'Subscription: '.$invoice->plan_name,
            'Cycle: '.ucfirst($invoice->billing_cycle),
            'List price: '.BillingMoney::format($invoice->list_price_minor),
            'Plan discount: -'.BillingMoney::format($invoice->plan_discount_minor),
            'Coupon discount: -'.BillingMoney::format($invoice->coupon_discount_minor),
            'Tax ('.number_format($invoice->tax_basis_points / 100,2).' percent): '.BillingMoney::format($invoice->tax_minor),
            'TOTAL: '.$invoice->currency.' '.BillingMoney::format($invoice->total_minor),
            '',
            'Payment status: '.($invoice->status === 'paid' ? 'ADMIN VERIFIED' : 'NOT VERIFIED'),
            'Payment reference: '.($invoice->payment?->review_reference ?: '-'),
            'Date verified: '.($invoice->paid_at?->format('Y-m-d H:i') ?: '-'),
            '',
            'A payment submission is not proof of cleared funds.',
            'Document generated from immutable billing snapshots.',
        ];
        return $this->render($rows);
    }

    public function creditNote(BillingRefund $refund): string
    {
        $invoice = $refund->invoice;
        return $this->render([
            'CREDIT NOTE - MANUALLY VERIFIED REFUND',
            'POS Cloud - Platform Billing',
            '',
            'Credit note: '.$refund->credit_note_number,
            'Invoice: '.$invoice->number,
            'Business: '.$invoice->bill_to_name,
            'Currency: '.$refund->currency,
            'Verified refund: '.$refund->currency.' '.BillingMoney::format($refund->amount_minor),
            'External settlement reference: '.$refund->external_reference,
            'Method: '.strtoupper($refund->method),
            'Date verified: '.$refund->verified_at?->format('Y-m-d H:i'),
            'Reason: '.$refund->reason,
            '',
            'This is an externally verified manual refund record.',
            'It does not initiate a card or bank transfer.',
        ]);
    }

    private function render(array $rows): string
    {
        $stream = "BT /F1 13 Tf 48 785 Td 19 TL\n";
        foreach ($rows as $index => $row) {
            // Single-page output, plain text, avoid PDF-control injection.
            $value = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$row);
            $value = preg_replace('/[^\x20-\x7E]/', ' ', $value ?: '');
            $value = substr($value,0,105);
            $value = str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $value);
            $stream .= ($index ? "T*\n" : '').'('.$value.") Tj\n";
            if ($index >= 31) break;
        }
        $stream .= "ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            "<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i+1)." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++) {
            $pdf .= sprintf("%010d 00000 n \n",$offsets[$i]);
        }
        return $pdf."trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
