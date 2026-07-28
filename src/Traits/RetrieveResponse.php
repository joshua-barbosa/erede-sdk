<?php

namespace eRede\Traits;

use eRede\Classes\ReturnResponse;
use eRede\Exceptions\eRedeException;
use eRede\Support\Redactor;
use Illuminate\Http\Client\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Converte a resposta HTTP da Rede em payload utilizável ou em exceção.
 *
 * A classe que usa este trait deve expor uma propriedade `$logger`.
 */
trait RetrieveResponse
{
    /**
     * @return array<string,mixed>
     *
     * @throws eRedeException
     */
    private function retrieveResponse(Response $response): array
    {
        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if ($response->successful()) {
            return $json;
        }

        $status = $response->status();

        $this->logger()->error('eRede error payment.', Redactor::scrub([
            'status' => $status,
            'return' => $json,
        ]));

        if ($status === HttpStatus::HTTP_UNAUTHORIZED) {
            throw new eRedeException(
                'Não foi possível conectar com o meio de pagamento.',
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
                $response->toException(),
                ['status' => $status],
            );
        }

        $returnCode = $this->returnCode($json);

        if ($returnCode !== null) {
            throw new eRedeException(
                ReturnResponse::getReturnMessage(code: $returnCode),
                $status,
                $response->toException(),
                ['status' => $status, 'return_code' => $returnCode],
            );
        }

        throw new eRedeException(
            'Falha na comunicação com o meio de pagamento.',
            $status,
            $response->toException(),
            ['status' => $status],
        );
    }

    /**
     * O código de retorno vem na raiz nas respostas de criação e dentro de
     * `authorization` nas de consulta.
     *
     * @param  array<string,mixed>  $json
     */
    private function returnCode(array $json): ?string
    {
        $code = $json['returnCode']
            ?? $json['authorization']['returnCode']
            ?? null;

        return $code === null ? null : (string) $code;
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger;
    }
}
