<?php

namespace App\Service;

use App\Entity\Unit;

final class UnitTreeBuilder
{
    /**
     * @param Unit[] $visibleUnits
     * @return array{0: array<int|null, Unit[]>}
     */
    public function buildVisibleTree(array $visibleUnits): array
    {
        $visibleById = [];
        foreach ($visibleUnits as $u) {
            $visibleById[$u->getId()] = $u;
        }

        $childrenByVisibleParent = [];

        foreach ($visibleUnits as $u) {
            $p = $u->getParent();                 // may be a proxy: OK
            $pid = $p ? $p->getId() : null;       // getId() does NOT initialize proxy

            // Only attach to parent if the parent is ALSO in the visible set
            $bucketKey = (null !== $pid && isset($visibleById[$pid])) ? $pid : null;

            $childrenByVisibleParent[$bucketKey][] = $u;
        }


        // Optional: sort each bucket by name (or by code)
        foreach ($childrenByVisibleParent as $pid => $bucket) {
            usort($bucket, fn(Unit $a, Unit $b) => strcmp($a->getName(), $b->getName()));
            $childrenByVisibleParent[$pid] = $bucket;
        }

        return [$childrenByVisibleParent];
        // paste the old logic here (from UnitController)
    }
}
