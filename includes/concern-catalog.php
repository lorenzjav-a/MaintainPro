<?php
declare(strict_types=1);

// Public, curated choices only. Never use private case notes as public suggestions.
final class ConcernCatalog
{
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
            'Roads and Infrastructure' => ['Inspect and measure the affected road or infrastructure section.', 'Consider temporary patching or barriers after the site inspection.', 'Schedule permanent repair of the damaged section.'],
            'Drainage/Flooding' => ['Inspect drainage inlets and locate the blockage or flood source.', 'Arrange safe removal of accumulated debris and check water flow.', 'Schedule repair or drainage improvements to reduce recurring flooding.'],
            'Waste Management' => ['Inspect the waste accumulation and identify collection needs.', 'Arrange appropriate waste collection and safe cleanup.', 'Review collection schedules and disposal guidance to prevent recurrence.'],
            'Street Lighting' => ['Have qualified personnel inspect the streetlight fixture and bulb.', 'Have qualified personnel check the electrical connection and wiring.', 'Schedule repair or replacement after identifying the fault.'],
            'Water' => ['Inspect the affected water connection and document the issue.', 'Coordinate inspection and repair with the water service provider.', 'Verify water service after the appropriate repair or corrective action.'],
            'Public Facilities' => ['Inspect the public facility and identify damaged components.', 'Restrict access to unsafe equipment pending assessment.', 'Schedule repair or replacement and check the facility before reopening.'],
            'Sanitation' => ['Inspect the affected area and identify the sanitation source.', 'Arrange safe cleaning and appropriate waste removal.', 'Schedule follow-up inspection to check whether the issue recurs.'],
            'Safety' => ['Arrange an assessment by the appropriate barangay response team.', 'Consider temporary barriers or access guidance after assessing the hazard.', 'Refer specialized hazards to the appropriate qualified agency.'],
            'Environmental Concern' => ['Inspect the environmental concern and its surrounding area.', 'Coordinate safe clearing or mitigation with qualified personnel.', 'Schedule follow-up monitoring and preventive maintenance.'],
            default => ['Inspect the reported area to understand the concern.', 'Identify the appropriate team or agency to assess the issue.', 'Prepare an action plan and follow-up inspection after assessment.'],
        };
        if ($type === 'Pothole') $defaults = ['Inspect and measure the pothole and damaged road section.', 'Consider temporary patching if immediate repair is needed.', 'Schedule permanent road repair or resurfacing after assessment.'];
        foreach ($rules as $rule) if ($rule['category'] === $category && $rule['concern_type'] === $type) $defaults = json_decode($rule['actions'], true, 8, JSON_THROW_ON_ERROR);
        if (array_intersect($points, ['Immediate danger', 'Sparks visible', 'Exposed wires', 'Near power lines'])) {
            $defaults[2] = 'Prioritize an urgent safety assessment by qualified personnel and restrict access where necessary.';
        } elseif (array_intersect($points, ['Near school', 'Near pedestrian crossing', 'High traffic area', 'Dangerous to motorcycles', 'Blocking access'])) {
            $defaults[2] .= ' Prioritize temporary safety measures because access or public movement may be affected.';
        }
        return array_values($defaults);
    }
}
