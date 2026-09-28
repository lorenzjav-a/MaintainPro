<?php
declare(strict_types=1);

// Central validated choices, resident guidance, and official decision-support solutions.
// Never derive either audience's suggestions from private case notes.
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
    public const OFFICIAL_SOLUTIONS = [
        'Roads and Infrastructure' => [
            'Deep' => ['Inspect and measure the depth of the damaged road section.', 'Apply safe temporary patching or barriers while permanent repair is scheduled.', 'Include the affected section in the barangay road repair schedule.'],
            'Wide' => ['Inspect and measure the full width of the road damage.', 'Mark or secure the damaged edges to guide road users safely.', 'Schedule a repair that covers the entire weakened road section.'],
            'Causing traffic' => ['Assess how the road condition is disrupting vehicle flow.', 'Provide temporary traffic controls or a safe alternate route.', 'Schedule clearing or repair during a period that limits traffic disruption.'],
            'Near school' => ['Inspect the damaged road and student access routes around the school.', 'Secure the area and provide a temporary safe route for students.', 'Schedule road work while minimizing disruption to school traffic.'],
            'Near intersection' => ['Inspect the road damage and visibility around the intersection.', 'Place appropriate warnings or temporary traffic controls at safe locations.', 'Coordinate repair while keeping intersection access and sight lines clear.'],
            'Flooded when raining' => ['Inspect the road drainage and locate where rainwater accumulates.', 'Clear nearby drainage paths or obstructions contributing to flooding.', 'Schedule drainage improvement or road repair for the affected section.'],
            'Dangerous to motorcycles' => ['Secure the hazard and provide clear warnings or a safe detour for riders.', 'Inspect the road surface for edges, holes, or debris that endanger motorcycles.', 'Prioritize repair of the riding surface and confirm that the route is safe.'],
            'Blocking access' => ['Inspect the road damage or obstruction blocking the route.', 'Provide a temporary safe passage or clearly marked alternate route.', 'Remove the obstruction or schedule the required road repair.'],
        ],
        'Drainage/Flooding' => [
            'Blocked with debris' => ['Schedule clearing of debris from the affected drainage section.', 'Inspect connected drainage lines for additional blockage.', 'Add repeatedly blocked locations to a preventive drainage cleaning schedule.'],
            'Overflowing' => ['Inspect the overflowing drain for blockage, damage, or limited capacity.', 'Clear the affected section and manage water flow safely.', 'Evaluate repairs or preventive drainage work where overflow keeps occurring.'],
            'Bad odor' => ['Inspect the drainage for stagnant water, waste, or hidden blockage.', 'Schedule cleaning of the affected drainage section.', 'Identify the odor source and add the location to preventive maintenance.'],
            'Flooded when raining' => ['Inspect drainage paths and low points that flood during rainfall.', 'Clear connected drains and channels before the next heavy rain.', 'Evaluate drainage improvements for repeatedly flooded locations.'],
            'Blocking access' => ['Clear drainage debris or standing water that is blocking the route.', 'Provide a safe temporary route while drainage work is underway.', 'Correct the drainage cause to prevent the pathway from being blocked again.'],
            'Near school' => ['Inspect drainage and student access routes around the school.', 'Clear blockage and standing water near school entrances or paths.', 'Schedule pre-rain cleaning and monitoring around the school area.'],
        ],
        'Waste Management' => [
            'Bad odor' => ['Inspect the location for uncollected or improperly stored waste.', 'Schedule collection and cleanup of the affected area.', 'Adjust collection frequency or monitoring if the odor returns.'],
            'Attracting pests' => ['Inspect and safely remove waste that is attracting pests.', 'Clean the area and coordinate appropriate pest control when needed.', 'Monitor the site and improve the collection schedule to prevent recurrence.'],
            'Recurring issue' => ['Clean and collect waste from the affected location.', 'Identify why waste repeatedly accumulates or is dumped at the site.', 'Create a preventive collection and monitoring schedule for the location.'],
            'Blocking access' => ['Remove accumulated waste that is blocking public access.', 'Clean the route and check for sharp or hazardous discarded materials.', 'Adjust collection or anti-dumping monitoring to keep the route clear.'],
            'Near school' => ['Prioritize removal of waste near the school area.', 'Inspect student routes for sanitation and pest hazards.', 'Adjust collection and monitoring around the school to prevent repeat buildup.'],
        ],
        'Street Lighting' => [
            'Completely dark' => ['Inspect the affected fixture, pole, and electrical supply.', 'Have qualified personnel repair or replace the failed lighting equipment.', 'Monitor the location and include repeated failures in the replacement schedule.'],
            'Near pedestrian crossing' => ['Inspect lighting coverage at the pedestrian crossing.', 'Have qualified personnel restore the light and check fixture alignment.', 'Prioritize reliable lighting for people using the crossing after dark.'],
            'High traffic area' => ['Inspect the light and electrical connections with traffic safety controls in place.', 'Have qualified personnel repair the fixture or damaged components.', 'Prioritize restoration because the location carries heavy traffic.'],
            'Sparks visible' => ['Secure the area around the sparking electrical equipment immediately.', 'Have qualified electrical personnel inspect and isolate the fault.', 'Coordinate urgent repair with the electricity provider or appropriate authority.'],
            'Exposed wires' => ['Secure the area and prevent contact with the exposed wiring.', 'Have qualified electrical personnel inspect, isolate, and repair the wiring.', 'Coordinate with the utility provider when the damaged line involves its equipment.'],
            'Near school' => ['Inspect the affected light and paths commonly used by students.', 'Have qualified personnel restore adequate lighting around the school.', 'Prioritize the work while avoiding disruption during peak school movement.'],
        ],
        'Water' => [
            'Continuous leak' => ['Inspect the water line and identify the source and rate of the leak.', 'Coordinate safe isolation or temporary control with qualified personnel.', 'Schedule permanent repair and monitor the location for further water loss.'],
            'Low pressure' => ['Check the affected connections, valves, and nearby supply conditions.', 'Coordinate a network inspection with the water service provider.', 'Monitor pressure after corrective work and record homes still affected.'],
            'Bad odor' => ['Inspect the reported water source and affected supply area.', 'Coordinate a water quality investigation for contamination or stagnant water.', 'Notify qualified water service personnel when testing or repair is required.'],
            'Recurring issue' => ['Review earlier water reports and inspect the repeated failure point.', 'Coordinate a permanent repair after identifying the underlying cause.', 'Add the line or supply area to preventive monitoring with the water provider.'],
            'Affecting several homes' => ['Confirm the extent of the water issue across affected households.', 'Coordinate prompt inspection with the water service provider.', 'Track service restoration and any locations that still require repair.'],
        ],
        'Public Facilities' => [
            'Sharp edges' => ['Inspect the facility for exposed sharp or broken surfaces.', 'Restrict access or install a safe temporary guard around the hazard.', 'Repair or replace the damaged facility component.'],
            'Unusable' => ['Inspect the facility and document why it cannot be used.', 'Close the affected section or direct users to a safe alternative.', 'Schedule repair or replacement before reopening the facility.'],
            'Near school' => ['Inspect the damaged facility and areas used by students.', 'Secure the hazard or redirect student access to a safe route.', 'Prioritize repair and schedule work around school use.'],
            'Blocking access' => ['Inspect the damaged facility component blocking the route.', 'Restrict or redirect access until the obstruction is safe.', 'Repair, reposition, or remove the obstruction as appropriate.'],
            'Recurring issue' => ['Review the facility repair history and inspect the current damage.', 'Identify the repeated failure or misuse causing the problem.', 'Create a preventive inspection or replacement schedule for the facility.'],
        ],
        'Sanitation' => [
            'Bad odor' => ['Inspect the location and identify the sanitation source of the odor.', 'Schedule appropriate cleaning and disinfection of the affected area.', 'Investigate repeated odor reports and correct the underlying sanitation issue.'],
            'Attracting pests' => ['Inspect the area for waste, standing water, or pest shelter.', 'Coordinate cleaning, waste removal, and appropriate pest control.', 'Add the location to preventive sanitation and pest monitoring.'],
            'Near school' => ['Inspect sanitation hazards around the school and student routes.', 'Schedule prompt cleaning and disinfection where appropriate.', 'Monitor the school area and prevent repeated waste or standing water buildup.'],
            'Recurring issue' => ['Clean and assess the affected sanitation area.', 'Identify why the sanitation problem continues to return.', 'Create a preventive cleaning, inspection, and monitoring schedule.'],
            'Affecting several homes' => ['Assess the sanitation problem across the affected households.', 'Coordinate a barangay sanitation response for the shared source.', 'Monitor the area after cleanup and address conditions that could spread the issue.'],
        ],
        'Safety' => [
            'Blocking access' => ['Assess the hazard that is blocking public access.', 'Restrict the unsafe route and direct people to a safe alternative.', 'Coordinate removal or repair with the responsible barangay team.'],
            'High traffic area' => ['Inspect the hazard promptly under safe traffic controls.', 'Install suitable warnings, barriers, or traffic guidance.', 'Coordinate corrective action with the responsible traffic or safety authority.'],
            'Near school' => ['Inspect the hazard and student exposure around the school.', 'Secure the area and redirect students to a safe route.', 'Prioritize corrective action with school and barangay personnel.'],
            'Immediate danger' => ['Secure the affected area and warn people away from the hazard.', 'Prevent public access and contact emergency services when necessary.', 'Arrange immediate assessment by barangay personnel or the proper authority.'],
            'Recurring issue' => ['Assess the current hazard and review earlier reports at the location.', 'Identify the cause of the repeated safety problem.', 'Create a preventive correction and monitoring schedule with the responsible authority.'],
        ],
        'Environmental Concern' => [
            'Blocking access' => ['Assess the tree, vegetation, debris, or pollution blocking the route.', 'Restrict access and provide a safe alternative route.', 'Coordinate safe removal or cleanup with qualified personnel.'],
            'Near power lines' => ['Secure the area and keep people away from the power line hazard.', 'Coordinate assessment with the utility provider and qualified personnel.', 'Proceed with trimming or removal only after electrical safety clearance.'],
            'Bad odor' => ['Inspect the suspected environmental source of the odor.', 'Determine whether waste, stagnant water, or pollution is responsible.', 'Coordinate proper cleanup or referral to the responsible authority.'],
            'Recurring issue' => ['Inspect the current environmental condition and earlier reports.', 'Identify the source that repeatedly causes the problem.', 'Create a preventive environmental maintenance and monitoring schedule.'],
            'Affecting several homes' => ['Determine the extent and source of the environmental impact.', 'Coordinate assessment with environmental or health authorities.', 'Plan mitigation and follow-up monitoring for the affected households.'],
        ],
        'Other' => [
            'Recurring issue' => ['Review the current report and earlier reports about the same concern.', 'Identify why the reported problem keeps returning.', 'Create a suitable preventive maintenance or monitoring schedule.'],
            'Blocking access' => ['Inspect the reported problem and how it blocks access.', 'Secure the area or provide a safe alternative route.', 'Coordinate removal, repair, or referral to the responsible office.'],
            'Near school' => ['Inspect the reported concern and its effect on the school area.', 'Secure the area or redirect students away from the problem.', 'Prioritize action or referral to the office responsible for the concern.'],
            'Immediate danger' => ['Secure the affected area and warn people away from the danger.', 'Prevent public access and contact emergency services when necessary.', 'Arrange immediate assessment by barangay personnel or the proper authority.'],
        ],
    ];
    public const OFFICIAL_DEFAULTS = [
        'Roads and Infrastructure' => ['Inspect the reported road or infrastructure concern.', 'Provide safe temporary controls where access is affected.', 'Schedule suitable repair and preventive road monitoring.'],
        'Drainage/Flooding' => ['Inspect the reported drainage or flooding concern.', 'Clear blockage or manage water flow based on site conditions.', 'Schedule preventive drainage cleaning or repair.'],
        'Waste Management' => ['Inspect the reported waste location.', 'Schedule safe collection and cleanup.', 'Review collection and monitoring needed to prevent repeat buildup.'],
        'Street Lighting' => ['Inspect the reported lighting concern safely.', 'Have qualified personnel complete the required electrical work.', 'Monitor the fixture and plan replacement if failures continue.'],
        'Water' => ['Inspect the reported water service concern.', 'Coordinate corrective work with qualified water personnel.', 'Monitor the affected supply and prevent repeated service problems.'],
        'Public Facilities' => ['Inspect the reported facility concern.', 'Secure the affected section or redirect users safely.', 'Schedule repair and preventive facility inspection.'],
        'Sanitation' => ['Inspect the reported sanitation concern.', 'Schedule appropriate cleaning and corrective work.', 'Monitor the location and prevent the issue from returning.'],
        'Safety' => ['Assess the reported safety concern promptly.', 'Secure the area and control public access when necessary.', 'Coordinate corrective and preventive action with the proper authority.'],
        'Environmental Concern' => ['Inspect the reported environmental concern.', 'Coordinate safe cleanup or mitigation with qualified personnel.', 'Monitor the location and address the source of repeated impacts.'],
        'Other' => ['Inspect the reported {type} and confirm its effects.', 'Secure the affected area or refer the concern to the proper office.', 'Plan suitable corrective and preventive follow-up.'],
    ];
    public const SOLUTION_ATTENTION = [
        'Immediate danger' => 4, 'Sparks visible' => 4, 'Exposed wires' => 4, 'Near power lines' => 4, 'Dangerous to motorcycles' => 4,
        'Blocking access' => 3, 'Near school' => 3, 'Near pedestrian crossing' => 3, 'Near intersection' => 3, 'High traffic area' => 3, 'Affecting several homes' => 3,
        'Recurring issue' => 2, 'Flooded when raining' => 2, 'Overflowing' => 2, 'Completely dark' => 2, 'Continuous leak' => 2, 'Attracting pests' => 2,
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

    public static function officialSuggestions(string $category, string $type, array $pointCounts, bool $recurringHistory = false): array
    {
        $configured = self::OFFICIAL_SOLUTIONS[$category] ?? [];
        $order = array_flip(self::POINTS[$category] ?? []);
        $counts = [];
        foreach ($pointCounts as $point => $count) {
            if (is_int($point)) { $point = is_string($count) ? $count : ''; $count = 1; }
            if (isset($configured[$point])) $counts[$point] = max(1, (int)$count);
        }
        // Frequency breaks ties inside the requested safety-attention order.
        $ranked = array_keys($counts);
        usort($ranked, fn(string $a, string $b): int => (self::SOLUTION_ATTENTION[$b] ?? 1) <=> (self::SOLUTION_ATTENTION[$a] ?? 1)
            ?: $counts[$b] <=> $counts[$a]
            ?: ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        $defaults = self::OFFICIAL_DEFAULTS[$category] ?? ['Inspect the reported concern and confirm its effects.', 'Prepare an appropriate response for official approval.', 'Monitor the location and prevent the issue from returning.'];
        $defaults = array_map(fn(string $action): string => str_replace('{type}', strtolower($type !== '' ? $type : 'concern'), $action), $defaults);
        if (!$ranked) return ['keyPoints' => [], 'actions' => $defaults];

        $recurring = in_array('Recurring issue', $ranked, true) || $recurringHistory;
        if (count($ranked) === 1 && !$recurringHistory) return ['keyPoints' => $ranked, 'actions' => $configured[$ranked[0]]];

        $candidates = [];
        $limit = $recurring ? 2 : 3;
        $primary = $ranked[0];
        if ((self::SOLUTION_ATTENTION[$primary] ?? 1) === 4) {
            $candidates[] = $configured[$primary][0];
            $candidates[] = $configured[$primary][1];
        } else {
            foreach ($ranked as $point) $candidates[] = $configured[$point][0];
        }
        $actions = self::distinctActions($candidates, $limit);
        foreach ($ranked as $point) {
            foreach ($configured[$point] as $action) $candidates[] = $action;
        }
        if ($recurring) {
            $preventive = isset($configured['Recurring issue']) ? $configured['Recurring issue'][2] : $defaults[2];
            $actions = self::distinctActions(array_merge($actions, $candidates, $defaults), 2);
            $actions = self::distinctActions(array_merge($actions, [$preventive]), 3);
        }
        $actions = self::distinctActions(array_merge($actions, $candidates, $defaults), 3);
        return ['keyPoints' => $ranked, 'actions' => $actions];
    }

    private static function distinctActions(array $candidates, int $limit): array
    {
        $actions = []; $seen = [];
        foreach ($candidates as $action) {
            $key = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $action)));
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true; $actions[] = $action;
            if (count($actions) === $limit) break;
        }
        return $actions;
    }

    public static function validGuidance(mixed $steps): bool
    {
        return is_array($steps) && array_is_list($steps) && count($steps) === 3
            && count(array_filter($steps, fn($step) => is_string($step) && trim($step) !== '')) === 3;
    }
}
