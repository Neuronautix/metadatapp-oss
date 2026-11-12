<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Mnms\Client;

use App\Entity\ConnectedApp;

final readonly class MnmsClient implements MnmsClientInterface
{
    public function searchFields(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $needle = strtolower($query);
        $results = [];

        foreach ($this->fields() as $field) {
            if (\count($results) >= $limit) {
                break;
            }
            if ('' === $needle || str_contains(strtolower($field['name'] . ' ' . $field['description']), $needle)) {
                $results[] = $field;
            }
        }

        return $results;
    }

    /** @return list<array{name: string, description: string, type: string, unit?: string|null}> */
    private function fields(): array
    {
        return [
            ['name' => 'RepetitionTime', 'description' => 'Time in seconds between the beginning of an acquisition of one volume and the beginning of acquisition of the volume following it (TR).', 'type' => 'number', 'unit' => 's'],
            ['name' => 'EchoTime', 'description' => 'The echo time (TE) for the acquisition, specified in seconds.', 'type' => 'number', 'unit' => 's'],
            ['name' => 'FlipAngle', 'description' => 'Flip angle for the acquisition, specified in degrees.', 'type' => 'number', 'unit' => 'degrees'],
            ['name' => 'MagneticFieldStrength', 'description' => 'Nominal field strength of the MR magnet in Tesla.', 'type' => 'number', 'unit' => 'T'],
            ['name' => 'Manufacturer', 'description' => 'Manufacturer of the equipment that produced the measurements.', 'type' => 'string', 'unit' => null],
            ['name' => 'ManufacturersModelName', 'description' => 'Manufacturer model name of the equipment that produced the measurements.', 'type' => 'string', 'unit' => null],
            ['name' => 'SoftwareVersions', 'description' => 'Manufacturer designation of software version of the equipment that produced the measurements.', 'type' => 'string', 'unit' => null],
            ['name' => 'ReceiveCoilName', 'description' => 'Information describing the receiver coil. Corresponds to DICOM Tag 0018, 1250 Receive Coil Name.', 'type' => 'string', 'unit' => null],
            ['name' => 'SliceThickness', 'description' => 'Excitation volume thickness, in mm.', 'type' => 'number', 'unit' => 'mm'],
            ['name' => 'SliceTiming', 'description' => 'The time at which each slice was acquired during the acquisition.', 'type' => 'array', 'unit' => 's'],
            ['name' => 'NumberOfVolumesDiscardedByScanner', 'description' => 'Number of volumes ("dummy scans") discarded by the scanner at the beginning of the acquisition.', 'type' => 'integer', 'unit' => null],
            ['name' => 'TaskName', 'description' => 'Name of the task. No two tasks in a dataset should have the same name.', 'type' => 'string', 'unit' => null],
            ['name' => 'TaskDescription', 'description' => 'Longer description of the task and the stimuli applied.', 'type' => 'string', 'unit' => null],
            ['name' => 'InstitutionName', 'description' => 'The name of the institution in charge of the equipment that produced the measurements.', 'type' => 'string', 'unit' => null],
            ['name' => 'InstitutionAddress', 'description' => 'The address of the institution in charge of the equipment that produced the measurements.', 'type' => 'string', 'unit' => null],
            ['name' => 'DeviceSerialNumber', 'description' => 'The serial number of the equipment that produced the measurements.', 'type' => 'string', 'unit' => null],
            ['name' => 'StationName', 'description' => 'Institution defined name of the machine that produced the measurements.', 'type' => 'string', 'unit' => null],
            ['name' => 'BodyPartExamined', 'description' => 'Body part examined. Corresponds to DICOM Tag 0018, 0015.', 'type' => 'string', 'unit' => null],
            ['name' => 'PatientPosition', 'description' => 'Position of the patient relative to the imaging equipment.', 'type' => 'string', 'unit' => null],
            ['name' => 'ScanningSequence', 'description' => 'Description of the type of data acquired. Corresponds to DICOM Tag 0018, 0020.', 'type' => 'string', 'unit' => null],
            ['name' => 'SequenceVariant', 'description' => 'Variant of the ScanningSequence. Corresponds to DICOM Tag 0018, 0021.', 'type' => 'string', 'unit' => null],
            ['name' => 'PixelBandwidth', 'description' => 'Bandwidth per pixel. The total bandwidth of the RF excitation pulse, in Hz.', 'type' => 'number', 'unit' => 'Hz'],
            ['name' => 'ParallelReductionFactorInPlane', 'description' => 'The parallel imaging (e.g. GRAPPA) factor. Use the denominator of the fraction of k-space encoded for each slice.', 'type' => 'number', 'unit' => null],
            ['name' => 'PhaseEncodingDirection', 'description' => 'Possible values: i, j, k, i-, j-, k-. The letters i, j, k correspond to the first, second and third axis of the data in the NIFTI file.', 'type' => 'string', 'unit' => null],
            ['name' => 'TotalReadoutTime', 'description' => 'The total readout time, defined as the time from the center of the first echo to the center of the last echo.', 'type' => 'number', 'unit' => 's'],
        ];
    }
}
