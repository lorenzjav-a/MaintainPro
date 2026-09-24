<?php
declare(strict_types=1);

// Public, curated choices only. Never use private case notes as public suggestions.
final class ConcernCatalog
{
    public const GUIDANCE_PURPOSE = 'resident_temporary_guidance_v1';
    public const TYPES = [
        'Roads and Infrastructure' => ['Pothole', 'Damaged road', 'Broken sidewalk', 'Obstruction', 'Damaged signage'],
        'Drainage/Flooding' => ['Blocked drainage', 'Flooding', 'Damaged drain cover'],
        'Waste Management' => ['Uncollected garbage', 'Illegal dumping', 'Overflowing waste', 'Improper disposal'],
        'Street Lighting' => ['Light not working', 'Flickering light', 'Damaged pole', 'Exposed wiring'],
        'Water' => ['Water leak', 'No water supply', 'Discolored water'],
        'Public Facilities' => ['Damaged playground', 'Damaged public building', 'Broken public equipment'],
        'Sanitation' => ['Standing dirty water', 'Unsanitary public area', 'Pest concern'],
        'Safety' => ['Unsafe structure', 'Blocked access', 'Traffic hazard', 'Stray animal'],
        'Environmental Concern' => ['Fallen tree', 'Overgrown vegetation', 'Pollution'],
        'Other' => ['Other community concern'],
    ];
    public const POINTS = [
        'Roads and Infrastructure' => ['Deep', 'Wide', 'Causing traffic', 'Near school', 'Near intersection', 'Flooded when raining', 'Dangerous to motorcycles', 'Blocking access'],
        'Drainage/Flooding' => ['Blocked with debris', 'Overflowing', 'Bad odor', 'Flooded when raining', 'Blocking access', 'Near school'],
        'Waste Management' => ['Bad odor', 'Attracting pests', 'Recurring issue', 'Blocking access', 'Near school'],
        'Street Lighting' => ['Completely dark', 'Near pedestrian crossing', 'High traffic area', 'Sparks visible', 'Exposed wires', 'Near school'],
        'Water' => ['Continuous leak', 'Low pressure', 'Bad odor', 'Recurring issue', 'Affecting several homes'],
        'Public Facilities' => ['Sharp edges', 'Unusable', 'Near school', 'Blocking access', 'Recurring issue'],
        'Sanitation' => ['Bad odor', 'Attracting pests', 'Near school', 'Recurring issue', 'Affecting several homes'],
        'Safety' => ['Blocking access', 'High traffic area', 'Near school', 'Immediate danger', 'Recurring issue'],
        'Environmental Concern' => ['Blocking access', 'Near power lines', 'Bad odor', 'Recurring issue', 'Affecting several homes'],
        'Other' => ['Recurring issue', 'Blocking access', 'Near school', 'Immediate danger'],
    ];
    public const WORK_STATUSES = ['Arrived at location', 'Inspection completed', 'Materials required', 'Repair started', 'Work ongoing', 'Waiting for materials', 'Temporarily repaired', 'Fully repaired', 'Unable to complete', 'Requires another team'];
    public const ACTIONS = ['Inspection', 'Cleaning', 'Removal', 'Repair', 'Replacement', 'Temporary repair', 'Permanent repair', 'Referral', 'Other'];
    public const BLOCK_REASONS = ['Waiting for Materials', 'Requires Another Team', 'Weather Delay', 'Equipment Unavailable', 'Requires Official Approval', 'Other'];

    public static function selections(array $data): array
    {
        $category = $data['category'] ?? '';
        $type = $data['concernType'] ?? '';
        if (!is_string($category) || !isset(self::TYPES[$category]) || !is_string($type) || !in_array($type, self::TYPES[$category], true)) {
            throw new DomainException('Select a category and one of its concern types.');
        }
        return [$category, $type, self::multiple($data['keyPoints'] ?? [], self::POINTS[$category], 'key points')];
    }

    public static function multiple(mixed $values, array $allowed, string $label, bool $required = false): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > count($allowed)) throw new DomainException('Choose valid ' . $label . '.');
        foreach ($values as $value) if (!is_string($value) || !in_array($value, $allowed, true)) throw new DomainException('Choose valid ' . $label . '.');
        if ($required && !$values) throw new DomainException('Select at least one ' . $label . '.');
        return array_values(array_unique($values));
    }

    public static function suggestions(string $category, string $type, array $points, array $rules = []): array
    {
        $defaults = match ($category) {
            'Roads and Infrastructure' => ['Use an undamaged sidewalk or another safe route around the affected area.', 'Keep children away from the damaged section. Do not enter traffic to place markers or attempt repairs.', 'Let household members know which route to avoid while the barangay arranges the repair.'],
            'Drainage/Flooding' => ['Stay out of floodwater and keep children and pets away from drains and standing water.', 'Use a dry, safe alternative route. Do not lift drain covers or reach into blocked drains.', 'Follow local flood and evacuation advisories. If water is rising around you, seek emergency help instead of waiting for this report.'],
            'Waste Management' => ['Keep children and pets away from the waste pile. Do not handle dumped, sharp or unknown waste.', 'Keep your own household rubbish in closed containers in a safe storage area, away from the reported pile and public paths.', 'Avoid adding waste to the affected spot. Follow the announced collection schedule while waiting for cleanup.'],
            'Street Lighting' => ['Use a well-lit alternative route where available, especially after dark.', 'Carry a flashlight if you need to walk nearby and use established crossings.', 'Keep away from the pole and wiring. Do not open the fixture, climb the pole or attempt a repair.'],
            'Water' => ['Keep children away from pooling water and use a dry route around slippery areas.', 'Keep clean water containers covered and use your available water carefully while service is affected.', 'Check your water provider for service updates and follow any water-use advisory. Do not handle damaged public pipes or valves.'],
            'Public Facilities' => ['Stop using the damaged equipment or affected part of the facility.', 'Keep children away from broken surfaces, loose parts and sharp edges.', 'Use another safe facility or route until staff confirm that the affected area can be used again.'],
            'Sanitation' => ['Avoid contact with dirty standing water or waste and keep children and pets away.', 'Keep your own food, drinking water and household rubbish covered to reduce exposure to pests.', 'Use a clean alternative area while waiting. Do not apply chemicals or attempt to clean unknown public waste.'],
            'Safety' => ['Move away from the unsafe area and use another safe route.', 'Tell people in your household to avoid the hazard without approaching it yourself.', 'If anyone is in immediate danger, contact local emergency services from a safe place. Do not wait for a report update.'],
            'Environmental Concern' => ['Keep away from fallen branches, unstable trees and the affected area.', 'Choose an alternative route and keep children and pets clear of the hazard.', 'Do not cut branches, move heavy debris or touch unknown substances. Wait for qualified responders.'],
            default => ['Keep a safe distance from the reported problem and avoid unnecessary contact with it.', 'Use an unaffected route or facility, and let your household know which area to avoid.', 'Follow barangay updates while waiting. Contact local emergency services if there is immediate danger.'],
        };
        if ($type === 'Stray animal') $defaults = ['Keep your distance from the animal; do not approach, chase, feed or try to catch it.', 'Keep children and pets away, and use another route if the animal is blocking your path.', 'Stay in a safe place while waiting for assistance. Contact local emergency services if someone is in immediate danger.'];
        if ($type === 'Discolored water') $defaults = ['Use a known safe drinking-water supply while the water quality is uncertain.', 'Keep the affected water out of food preparation and drinking-water containers until the provider advises it is safe.', 'Check your water provider for water-quality instructions. Do not assume that clear-looking water is safe after the discoloration stops.'];
        if ($type === 'No water supply') $defaults = ['Use your remaining safe water carefully for essential household needs.', 'Keep stored drinking water in clean, covered containers.', 'Check the water provider or barangay announcements for a safe temporary water source and service updates.'];
        foreach ($rules as $rule) {
            if ($rule['category'] !== $category || $rule['concern_type'] !== $type) continue;
            $saved = json_decode($rule['actions'], true, 8);
            // Old triples describe staff work plans. Preserve them in storage, but never publish them as resident guidance.
            if (($saved['purpose'] ?? null) === self::GUIDANCE_PURPOSE && self::validGuidance($saved['steps'] ?? null)) $defaults = $saved['steps'];
        }
        if (in_array($type, ['Exposed wiring', 'Damaged pole'], true) || array_intersect($points, ['Sparks visible', 'Exposed wires', 'Near power lines'])) {
            return ['Stay well away from damaged poles, exposed or fallen wires, and anything touching them, including water and branches.', 'Keep children and pets away and choose another route. Do not touch, move or attempt to repair wires or poles.', 'Contact the electricity provider or local emergency services from a safe place. Do not wait for this concern to be assigned.'];
        }
        if (in_array('Immediate danger', $points, true)) {
            $defaults[2] = 'Move to a safe place and contact local emergency services now. Do not attempt a repair or wait for a report update.';
        } elseif (in_array('Blocking access', $points, true)) {
            $defaults[2] .= ' Use another safe entrance or route; do not move the obstruction yourself.';
        } elseif (array_intersect($points, ['Near school', 'Near pedestrian crossing', 'High traffic area', 'Dangerous to motorcycles', 'Near intersection'])) {
            $defaults[2] .= ' Help children choose a safe route away from the affected area without entering traffic.';
        }
        return array_values($defaults);
    }

    public static function validGuidance(mixed $steps): bool
    {
        return is_array($steps) && array_is_list($steps) && count($steps) === 3
            && count(array_filter($steps, fn($step) => is_string($step) && trim($step) !== '')) === 3;
    }
}
