<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'user_id',
        'customer_type',
        'email',
        'phone',
        'name',
        'surname',
        'company',
        'activity',
        'tax_code',
        'vat_code',
        'sdi_code',
        'ipa_code',
        'cig_code',
        'shipping_address_id',
        'billing_address_id',
        'pec',
        'shared_notes',
        'internal_notes',
    ];

    public const TYPE_PRIVATE = 'Privato';

    public const TYPE_PUBLIC_ADMIN = 'Pubblica amministrazione';

    /** Valori ammessi per `customer_type` (allineati alle option dei form frontend). */
    public const CUSTOMER_TYPES = [
        'Azienda',
        'Associazione / Comitato',
        self::TYPE_PUBLIC_ADMIN,
        'Sindacato',
        self::TYPE_PRIVATE,
    ];

    public const PROVINCES = [
        'AG', 'AL', 'AN', 'AO', 'AR', 'AP', 'AT', 'AV', 'BA', 'BT', 'BL', 'BN', 'BG', 'BI', 'BO', 'BZ', 'BS', 'BR', 'CA', 'CL', 'CB', 'CI', 'CE', 'CT', 'CZ', 'CH', 'CO', 'CS', 'CR', 'KR', 'CN', 'EN', 'EE', 'FM', 'FE', 'FI', 'FG', 'FC', 'FR', 'GE', 'GO', 'GR', 'IM', 'IS', 'SP', 'AQ', 'LT', 'LE', 'LC', 'LI', 'LO', 'LU', 'MC', 'MN', 'MS', 'MT', 'ME', 'MI', 'MO', 'MB', 'NA', 'NO', 'NU', 'OT', 'OR', 'PD', 'PA', 'PR', 'PV', 'PG', 'PU', 'PE', 'PC', 'PI', 'PT', 'PN', 'PZ', 'PO', 'RG', 'RA', 'RC', 'RE', 'RI', 'RN', 'RM', 'RO', 'SA', 'VS', 'SS', 'SV', 'SI', 'SR', 'SO', 'TA', 'TE', 'TR', 'TO', 'OG', 'TP', 'TN', 'TV', 'TS', 'UD', 'VA', 'VE', 'VB', 'VC', 'VR', 'VV', 'VI', 'VT',
    ];

    public static $view_fields = [
        'Nominativo' => 'full_name',
        'Tipo' => 'customer_type',
        'Email' => 'email',
        'Telefono' => 'phone',
        'Ragione sociale' => 'company',
        'Settore attività' => 'activity',
        'Codice fiscale' => 'tax_code',
        'Partita IVA' => 'vat_code',
        'Codice SDI' => 'sdi_code',
        'Codice IPA' => 'ipa_code',
        'Codice CIG' => 'cig_code',
        'PEC' => 'pec',
        'Note condivise' => 'shared_notes',
        'Note interne' => 'internal_notes',
    ];

    /** Etichette settore (value del select = stessa stringa). */
    public static array $activities = [
        'Agroalimentare',
        'Altre',
        'Arte-musica-spettacolo',
        'Assicurazioni',
        'Automotive-concessionarie',
        'Banche-credito',
        'Bar-gelaterie',
        'Comunicazione-eventi-agenzie-grafica',
        'Consulenza',
        'Cooperativa',
        'Cosmesi',
        'Culto',
        'Design-progettazione-arredamento',
        'Edilizia',
        'Editoria-radio-discografiche',
        'Energia',
        'Esercizi commerciali',
        'Farmaceutiche-elettromedicali',
        'Hotel-ospitalità',
        'Immobiliari',
        'Import-export',
        'Industria',
        'IT-informatica',
        'Moda-abbigliamento-accessori',
        'Musei-gallerie',
        'Ospedali',
        'Palestre-sport-benessere',
        'PMI',
        'Recruiting',
        'Ristorazione',
        'Rivenditori-grossisti',
        'Scuole-formazione',
        'Telecomunicazioni',
        'Terzo settore',
        'Trasporti-logistica',
        'Turismo',
        'Uffici legali',
    ];

    public function shipping_address(): HasOne
    {
        return $this->hasOne(CustomerAddress::class, 'id', 'shipping_address_id');
    }

    public function billing_address(): HasOne
    {
        return $this->hasOne(CustomerAddress::class, 'id', 'billing_address_id');
    }

    public function getFullNameAttribute()
    {
        return "{$this->name} {$this->surname}";
    }

    public static function requiresVat(?string $customerType): bool
    {
        return $customerType !== null && $customerType !== '' && $customerType !== self::TYPE_PRIVATE;
    }

    public static function isPublicAdmin(?string $customerType): bool
    {
        return $customerType === self::TYPE_PUBLIC_ADMIN;
    }

    public static function normalizedIpaForStore(?string $customerType, ?string $ipa): ?string
    {
        return self::isPublicAdmin($customerType) ? $ipa : null;
    }

    public static function normalizedCigForStore(?string $customerType, ?string $cig): ?string
    {
        return self::isPublicAdmin($customerType) ? $cig : null;
    }

    public static function normalizedVatForStore(?string $customerType, ?string $vat): ?string
    {
        return self::requiresVat($customerType) ? $vat : null;
    }

    public function getShippingAddressAttribute()
    {
        // Controlla se la relazione è già caricata
        if ($this->relationLoaded('shipping_address')) {
            return $this->getRelation('shipping_address');
        }

        // Carica la relazione SENZA chiamare l'accessor
        $shipping_address = $this->getRelationValue('shipping_address');

        // Se non esiste, creala
        if (! $shipping_address) {
            $shipping_address = $this->shipping_address()->create();
            $this->setRelation('shipping_address', $shipping_address);
        }

        return $shipping_address;
    }

    public function getBillingAddressAttribute()
    {
        // Controlla se la relazione è già caricata
        if ($this->relationLoaded('billing_address')) {
            return $this->getRelation('billing_address');
        }

        // Carica la relazione SENZA chiamare l'accessor
        $billing_address = $this->getRelationValue('billing_address');

        // Se non esiste, creala
        if (! $billing_address) {
            $billing_address = $this->billing_address()->create();
            $this->setRelation('billing_address', $billing_address);
        }

        return $billing_address;
    }
}
