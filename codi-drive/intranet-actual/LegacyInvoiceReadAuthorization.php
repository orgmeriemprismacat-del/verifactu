<?php

final class LegacyInvoiceReadAuthorization
{
    public static function assertCanView($user, string $page = '/alumnes/factura/'): void
    {
        if (!is_object($user) || !method_exists($user, 'tePermisVisualitzacio')) {
            throw new RuntimeException('No es pot validar el permís de consulta', 403);
        }

        $connection = new ConnexioIntranet();

        try {
            $connection->connectarBD();
            $stmt = $connection->prepare(
                'SELECT ROLS_VISUALITZAR FROM apartats WHERE URL = ? LIMIT 1'
            );
            $stmt->bind_param('s', $page);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows() <= 0) {
                $connection->closeStmt();
                throw new RuntimeException('No s’ha trobat la política de visualització', 403);
            }

            $stmt->bind_result($rolesVisualitzar);
            $stmt->fetch();
            $connection->closeStmt();

            $rolesVisualitzar = trim((string) $rolesVisualitzar);
            if ($rolesVisualitzar === '' || !$user->tePermisVisualitzacio($rolesVisualitzar)) {
                throw new RuntimeException('No tens permisos per consultar factures', 403);
            }
        } finally {
            if (isset($connection->connexio) && $connection->connexio instanceof mysqli) {
                $connection->desconectarBD();
            }
        }
    }
}
