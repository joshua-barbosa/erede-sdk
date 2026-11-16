<?php

namespace eRede\Responses;

use eRede\Classes\Link;
use eRede\Classes\Refund;
use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * @property Refund|null $refunds
 * @property array<int,Refund> $refundsList
 * @property array<int,Link>|null $links
 */
class RefundGet
{
    use Attribute;
    use ToArray {
        toArray as private traitToArray;
    }

    private ?Refund $refunds = null;

    /** @var array<int,Refund> */
    private array $refundsList = [];

    private ?array $links = null;

    public function __construct(
        ?Refund $refunds = null,
        ?array $links = null,
        ?array $fromData = null
    ) {
        $this->refunds = $refunds;
        $this->links = $links;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                if ($key === 'links') {
                    if (is_array($value)) {
                        foreach ($value as $linkData) {
                            $this->addLink(new Link(fromData: $linkData));
                        }
                    }
                } elseif ($key === 'refunds') {
                    $this->hydrateRefunds($value);
                } else {
                    $this->set(key: $key, value: $value);
                }
            }
        }
    }

    /**
     * A Rede devolve `refunds` como lista; versões anteriores do SDK tratavam
     * a chave como um único objeto. `refunds` continua apontando para o
     * primeiro item e `refundsList` guarda todos.
     */
    private function hydrateRefunds(mixed $value): void
    {
        $value = is_array($value) ? $value : [];
        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
        $items = $isList ? $value : [$value];

        $this->refundsList = array_map(
            static fn ($refundData) => new Refund(fromData: is_array($refundData) ? $refundData : []),
            $items,
        );

        $this->refunds = $this->refundsList[0] ?? new Refund(fromData: []);
    }

    /**
     * `refundsList` é só uma visão interna de `refunds`; fica fora da
     * serialização para o array continuar no formato devolvido pelo SDK.
     *
     * @return array<string,mixed>
     */
    public function toArray(bool $ignoreNullable = true, bool $toSnakeCase = false): array
    {
        $array = $this->traitToArray($ignoreNullable, $toSnakeCase);

        unset($array['refundsList'], $array['refunds_list']);

        return $array;
    }

    /**
     * Todas as devoluções da transação.
     *
     * @return array<int,Refund>
     */
    public function getRefundsList(): array
    {
        return $this->refundsList;
    }

    public function getRefunds(): ?Refund
    {
        return $this->refunds;
    }

    public function setRefunds(?Refund $refunds): self
    {
        $this->refunds = $refunds;

        return $this;
    }

    public function getLinks(): ?array
    {
        return $this->links;
    }

    public function setLinks(?array $links): self
    {
        $this->links = $links;

        return $this;
    }

    public function addLink(Link $link): self
    {
        if (is_null($this->links)) {
            $this->links = [];
        }
        $this->links[] = $link;

        return $this;
    }
}
