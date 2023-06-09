<?php

namespace Drupal\csv_importer\Plugin\Importer;

use Drupal\csv_importer\Plugin\ImporterBase;

/**
 * Class to import nodes.
 *
 * @Importer(
 *   id = "node_importer",
 *   entity_type = "node",
 *   label = @Translation("Node importer")
 * )
 */
class NodeImporter extends ImporterBase {}
