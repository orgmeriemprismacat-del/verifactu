<?php

final class Uc002SifPaymentRenderer
{
    public function renderSearchResult(array $preview): string
    {
        $invoice = $this->invoice($preview);
        $id = 'sif' . substr(
            hash('sha256', (string) $invoice['uuid_factura']),
            0,
            12
        );

        $numVisible = $this->e($invoice['num_visible']);
        $year = (int) ($invoice['any_fact'] ?? 0);
        $month = $this->month((string) ($invoice['data_emissio'] ?? ''));
        $nif = $this->e($invoice['billing']['nif'] ?? '');
        $name = $this->e($invoice['billing']['name'] ?? '');
        $total = $this->money($invoice['total'] ?? null);
        $paid = $this->money($invoice['net_paid'] ?? null);
        $pending = $this->money($invoice['pending_amount'] ?? null);
        $status = $this->e($invoice['estat_cobrament'] ?? '');
        $canPay = ($invoice['can_register_payment'] ?? false) === true;

        $paymentCell = $canPay
            ? '<td id="pagament-' . $id . '"><input type="text" class="form-control text-center pagament" value="'
                . $this->e($pending) . '"></td>'
            : '<td id="pagament-' . $id . '">0.00</td>';

        $actionCell = $canPay
            ? '<i id="upd-insc-' . $id . '" class="material-icons upd-inscripcio pointer">check_circle</i>'
            : '<span class="label lightGreen">PAGADA</span>';

        $bank = '<select id="banc-' . $id . '" class="form-control banc">'
            . '<option value="">Triar</option>'
            . '<option value="Caixa">Caixa</option>'
            . '<option value="BBVA">BBVA</option>'
            . '</select>';

        return '<div class="d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header">'
            . '<p class="title font-weight-bold text-center py-3 mb-0">Resultat SIF · ' . $numVisible . '</p>'
            . '</div>'
            . '<div class="card-body px-0 ps">'
            . '<div class="alert alert-info mx-3">Factura fiscal SIF. El cobrament es registrarà al ledger SIF abans de sincronitzar la intranet.</div>'
            . '<div class="d-flex flex-column flex-sm-row align-items-center justify-content-start w-100">'
            . '<table class="table table-hover table-striped text-center" style="min-width: 1200px">'
            . '<thead><tr>'
            . '<th>TIPUS</th><th>ANY</th><th>MES</th><th>CURS</th><th>DNI</th>'
            . '<th>A PAGAR</th><th>PAGAT</th><th>PAGAMENT</th><th>DATA PAG</th>'
            . '<th>BANC</th><th>OBSERVACIONS</th><th>FRACCIÓ</th><th>ACCIONS</th>'
            . '</tr></thead><tbody><tr>'
            . '<td><span class="label lightBlue">SIF</span></td>'
            . '<td>' . $year . '</td>'
            . '<td>' . $month . '</td>'
            . '<td>SIF</td>'
            . '<td title="' . $name . '">' . $nif . '</td>'
            . '<td id="apagar-' . $id . '">' . $this->e($total) . ' €</td>'
            . '<td id="pagat-' . $id . '">' . $this->e($paid) . ' €</td>'
            . $paymentCell
            . '<td><input type="date" id="dataPag-' . $id . '" class="form-control text-center dataPag"></td>'
            . '<td>' . $bank . '</td>'
            . '<td id="pagObs-' . $id . '">SIF ' . $numVisible . ' · ' . $status . '</td>'
            . '<td id="fraccio-' . $id . '">-</td>'
            . '<td>' . $actionCell . '</td>'
            . '<td id="pagat-inici-' . $id . '" class="d-none">' . $this->e($paid) . ' €</td>'
            . '<td id="tipus-' . $id . '" class="d-none">SIF</td>'
            . '<td id="efact-' . $id . '" class="d-none">1</td>'
            . '<td id="numFact-' . $id . '" class="d-none">' . $numVisible . '</td>'
            . '</tr></tbody></table></div></div>'
            . $this->modalShell();
    }

    public function renderConfirmation(array $preview): string
    {
        $invoice = $this->invoice($preview);
        $billing = is_array($invoice['billing'] ?? null) ? $invoice['billing'] : [];

        return '<div class="d-flex flex-column align-items-center w-100">'
            . '<p class="titol-apartat">Confirmació de cobrament SIF</p>'
            . '<div class="apartat w-100">'
            . '<p><strong>Factura:</strong> ' . $this->e($invoice['num_visible'] ?? '') . '</p>'
            . '<p><strong>Receptor:</strong> ' . $this->e($billing['name'] ?? '') . '</p>'
            . '<p><strong>NIF/CIF:</strong> ' . $this->e($billing['nif'] ?? '') . '</p>'
            . '<p><strong>Total fiscal:</strong> ' . $this->e($this->money($invoice['total'] ?? null)) . ' €</p>'
            . '<p><strong>Cobrat al SIF:</strong> ' . $this->e($this->money($invoice['net_paid'] ?? null)) . ' €</p>'
            . '<p><strong>Pendent:</strong> ' . $this->e($this->money($invoice['pending_amount'] ?? null)) . ' €</p>'
            . '<p><strong>Estat:</strong> ' . $this->e($invoice['estat_cobrament'] ?? '') . '</p>'
            . '</div>'
            . '<div class="d-flex justify-content-center mt-3">'
            . '<button id="torna-pagament" type="button" class="btn btn-secondary mr-2">Tornar</button>'
            . '<button id="confirmar-pagament" type="button" class="btn btn-success">Confirmar pagament</button>'
            . '</div></div>';
    }

    public function renderError(string $message): string
    {
        return '<div class="card-body px-3">'
            . '<div class="alert alert-danger text-center">'
            . $this->e($message)
            . '</div></div>';
    }

    private function invoice(array $preview): array
    {
        $invoice = $preview['invoice'] ?? null;
        if (!is_array($invoice)) {
            throw new RuntimeException('Resposta SIF de factura incompleta', 502);
        }

        return $invoice;
    }

    private function modalShell(): string
    {
        return '<div class="modal fade" id="confPag" tabindex="-1" role="dialog" aria-hidden="true">'
            . '<div class="modal-dialog" role="document"><div class="modal-content">'
            . '<div class="modal-body"></div><div class="modal-footer"></div>'
            . '</div></div></div>';
    }

    private function month(string $date): string
    {
        if (preg_match('/^\d{4}-(\d{2})-\d{2}/D', $date, $matches) === 1) {
            return $matches[1];
        }

        return '-';
    }

    private function money(mixed $value): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw new RuntimeException('Import SIF no vàlid per renderitzar', 502);
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
