<?php

/**
 * Class ConstructionStagesCreate
 *
 * This class represents a data transfer object (DTO) for creating construction stages.
 */
class ConstructionStagesCreate extends ValidationDTO
{
    public $name;
    public $startDate;
    public $endDate;
    public $duration;
    public $durationUnit;
    public $color;
    public $externalId;
    public $status;

    /**
     * ConstructionStagesCreate constructor.
     *
     * @param mixed $data An object containing data to populate the properties of this class.
     */
    public function __construct($data)
    {
        if (is_object($data)) {
            $vars = get_object_vars($this);
            foreach ($vars as $name => $value) {
                if (isset($data->$name)) {
                    $this->$name = $data->$name;
                }
            }
        }
    }

    /**
     * Define validation rules for the properties of this DTO.
     *
     * @return string[] An associative array where keys represent property names and values are validation rules.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'startDate' => 'required|date|isISO8601',
            'endDate' => 'date|isISO8601|after:{startDate}',
            'durationUnit' => 'in:' . implode(',', ['HOURS', 'DAYS', 'WEEKS']),
            'color' => 'hex_color',
            'externalId' => 'max:255',
            'status' => 'required|in:' . implode(',', ['NEW', 'PLANNED', 'DELETED']),
        ];
    }
}