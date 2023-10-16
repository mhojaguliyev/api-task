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

        $stmt = $this->db->prepare(
            "
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			"
        );
        $stmt->execute([
            'name' => $data->name,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'duration' => $data->duration,
            'durationUnit' => $data->durationUnit,
            'color' => $data->color,
            'externalId' => $data->externalId,
            'status' => $data->status,
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
}