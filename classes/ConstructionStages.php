<?php

/**
 * Class ConstructionStages
 *
 * Class is responsible for handling HTTP requests related to construction stages.
 */
class ConstructionStages
{
    private $db;

    /**
     * ConstructionStages constructor.
     */
    public function __construct()
    {
        $this->db = Api::getDb();
    }

    /**
     * List all construction stages
     *
     * @return array|false
     */
    public function getAll()
    {
        $stmt = $this->db->prepare(
            "
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
		"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve single construction stage
     *
     * @param $id
     * @return array
     *
     * @throws ConstructionStageNotfoundException
     */
    public function getSingle($id): array
    {
        $stmt = $this->db->prepare(
            "
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
			WHERE ID = :id
		"
        );
        $stmt->execute(['id' => $id]);
        $stage = $stmt->fetch(PDO::FETCH_ASSOC);

        // stage not found
        if (!$stage) {
            throw new ConstructionStageNotfoundException;
        }

        return $stage;
    }

    /**
     * Create new construction stage
     *
     * @param ConstructionStagesCreate $data
     * @return array
     * @throws ConstructionStageNotfoundException
     * @throws ValidationException
     */
    public function post(ConstructionStagesCreate $data): array
    {
        // validate inputs
        $validator = new Validator();
        $validator->validate($data);

        if ($validator->fails()) {
            throw new ValidationException($validator->getErrors());
        }
        $validatedAttributes = $validator->getValidated();

        // duration calculation
        $validatedAttributes['duration'] = $this->calculateDuration(
            $validatedAttributes['startDate'],
            $validatedAttributes['endDate'] ?? null,
            $validatedAttributes['durationUnit'] ?? 'DAYS'
        );

        $stmt = $this->db->prepare(
            "
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			"
        );
        $stmt->execute([
            'name' => $validatedAttributes['name'],
            'start_date' => $validatedAttributes['startDate'],
            'end_date' => $validatedAttributes['endDate'] ?? null,
            'duration' => $validatedAttributes['duration'],
            'durationUnit' => $validatedAttributes['durationUnit'] ?? ($validatedAttributes['duration'] ? 'DAYS' : null),
            'color' => $validatedAttributes['color'] ?? null,
            'externalId' => $validatedAttributes['externalId'] ?? null,
            'status' => $validatedAttributes['status'],
        ]);
        return $this->getSingle($this->db->lastInsertId());
    }

    /**
     * Update single construction stage
     *
     * @param ConstructionStagesUpdate $data
     * @param $id
     * @return array
     * @throws ConstructionStageNotfoundException
     * @throws ValidationException
     */
    public function patch(ConstructionStagesUpdate $data, $id): array
    {
        // fetch construction stage
        $stage = $this->getSingle($id);

        // validate inputs
        $validator = new Validator();
        $validator->setOnlyRequest(true)->validate($data);

        if ($validator->fails()) {
            throw new ValidationException($validator->getErrors());
        }

        if (count($validator->getValidated()) > 0) {
            $validatedAttributes = $validator->getValidated();
            $validatedAttributes['id'] = $id;

            // duration calculation
            $validatedAttributes['duration'] = $this->calculateDuration(
                $validatedAttributes['startDate'] ?? $stage['startDate'],
                $validatedAttributes['endDate'] ?? $stage['endDate'] ?? null,
                $validatedAttributes['durationUnit'] ?? $stage['durationUnit'] ?? 'DAYS'
            );
            if ($validatedAttributes['duration'] && empty($validatedAttributes['durationUnit'])) {
                $validatedAttributes['durationUnit'] = $validatedAttributes['durationUnit'] ?? $stage['durationUnit'] ?? 'DAYS';
            }

            // prepare sql
            $columns = '';
            foreach ($validatedAttributes as $key => $value) {
                $column = $key;
                if (strpos($column, 'Date') !== false) {
                    $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $column));
                }
                $columns .= "$column = :$key,";
            }
            $columns = trim($columns, ',');

            $sql = "UPDATE construction_stages SET $columns WHERE id = :id";

            // statement execution
            $stmt = $this->db->prepare($sql);
            $stmt->execute($validatedAttributes);
        }

        return $this->getSingle($id);
    }

    /**
     * Mark construction stage as deleted
     *
     * @param $id
     * @return string[]
     * @throws ConstructionStageNotfoundException
     * @throws RuntimeException
     */
    public function delete($id): array
    {
        $this->getSingle($id);

        $stmt = $this->db->prepare("UPDATE construction_stages SET status = :status WHERE id = :id");
        $bindings = ['id' => $id, 'status' => 'DELETED'];
        if ($stmt->execute($bindings)) {
            return ['message' => 'Deleted successfully'];
        }

        return ['message' => 'Failed to delete'];
    }

    /**
     * Calculate the duration between a start date and an end date based on the specified duration unit.
     *
     * @param string $start_date The start date in ISO8601 format (e.g., '2022-12-31T14:59:00Z').
     * @param string|null $end_date The end date in ISO8601 format (or null if no end date).
     * @param string $durationUnit The unit of duration ('HOURS', 'DAYS', or 'WEEKS', default is 'DAYS').
     *
     * @return float|null The calculated duration in the specified unit (null if invalid input).
     *
     * @throws Exception If the input date format is not valid.
     */
    private function calculateDuration($start_date, $end_date, $durationUnit = 'DAYS')
    {
        if (!$end_date) {
            return null;
        }

        // start and end date timestamp
        $startTimestamp = strtotime($start_date);
        $endTimestamp = strtotime($end_date);

        // Validate the start_date and end_date
        if (!$startTimestamp || !$endTimestamp) {
            return null;
        }

        // Initialize the duration to null.
        $duration = null;

        // Calculate duration based on the provided durationUnit.
        if ($endTimestamp > $startTimestamp) {
            switch ($durationUnit) {
                case 'HOURS':
                    // Calculate duration in hours.
                    $duration = intval(($endTimestamp - $startTimestamp) / 3600); // Hours
                    break;
                case 'DAYS':
                    // Calculate duration in days (default).
                    $duration = intval(($endTimestamp - $startTimestamp) / 86400); // Days
                    break;
                case 'WEEKS':
                    // Calculate duration in weeks.
                    $duration = intval(($endTimestamp - $startTimestamp) / 604800); // Weeks
                    break;
            }
        }

        return $duration;
    }
}