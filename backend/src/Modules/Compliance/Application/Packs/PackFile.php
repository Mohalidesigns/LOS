<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application\Packs;

use Fundly\Modules\Compliance\Domain\Packs\JurisdictionPack;
use Fundly\Modules\Compliance\Domain\PrudentialParameters;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Platform\Contracts\ConfigVersionView;
use Fundly\Modules\Platform\Contracts\LegalEntityView;
use Fundly\Modules\Platform\Contracts\OrganisationDirectory;

/**
 * Resolves the active jurisdiction pack and prudential parameters of a
 * booking legal entity (LOS-FR-310, LOS-FR-307). A pack version whose
 * content names another jurisdiction or licence than its key is ignored.
 */
final class PackFile
{
    public const PACK_TYPE = 'jurisdiction.pack';

    public const PRUDENTIAL_TYPE = 'prudential.parameters';

    public function __construct(private readonly ActiveConfiguration $config, private readonly OrganisationDirectory $organisation) {}

    public function legalEntity(string $id): ?LegalEntityView
    {
        return $this->organisation->legalEntity($id);
    }

    /** @return array{0: JurisdictionPack, 1: ConfigVersionView, 2: string}|null pack, version, key */
    public function pack(string $legalEntityId): ?array
    {
        $le = $this->legalEntity($legalEntityId);
        if ($le === null) {
            return null;
        }
        $key = JurisdictionPack::key($le->jurisdiction, $le->licenceCategory);
        $v = $this->config->activeVersion(self::PACK_TYPE, $key);
        if ($v === null || ($v->content['jurisdiction'] ?? null) !== strtoupper($le->jurisdiction) || ($v->content['licence_category'] ?? null) !== $le->licenceCategory) {
            return null;
        }

        return [JurisdictionPack::of($v->content), $v, $key];
    }

    /** @return array{0: PrudentialParameters, 1: ConfigVersionView}|null */
    public function prudential(string $legalEntityId): ?array
    {
        $le = $this->legalEntity($legalEntityId);
        $v = $le === null ? null : $this->config->activeVersion(self::PRUDENTIAL_TYPE, strtolower($le->code));

        return $v === null ? null : [PrudentialParameters::of($v->content), $v];
    }

    public static function allowUnverified(): bool
    {
        return config('fundly.compliance.allow_unverified_packs') === true && config('fundly.installation.environment') !== 'production';
    }
}
