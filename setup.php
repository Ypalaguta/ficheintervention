<?php
function plugin_version_ficheintervention() {
   
   //return array('name'       => _n('Fiche intervention', 'Fiches intervention', 1, 'fiche intervention'),
   return array('name'       => 'Fiche intervention',
            'version'        => '1.0',
            'author'         => 'Emmanuel Haguet - <a href="http://www.teclib.com">Teclib\'</a>',
            'homepage'       => 'http://www.teclib.com',
            'license'        => '',
            'minGlpiVersion' => "9.1");
}

/**
 * Check plugin's prerequisites before installation
 */
function plugin_ficheintervention_check_prerequisites() {
   if (version_compare(GLPI_VERSION,'9.1','lt') || version_compare(GLPI_VERSION,'9.2','ge')) {
      echo __('This plugin requires GLPI >= 9.1 and GLPI < 9.2', 'ficheintervention');
      return false;
   } else {
      return true;
   }

}

/**
 * Check plugin's config before activation
 */
function plugin_ficheintervention_check_config($verbose=false) {
   return true;
}

function plugin_init_ficheintervention() {
   global $PLUGIN_HOOKS;
   
   $PLUGIN_HOOKS['csrf_compliant']['ficheintervention'] = true;
   
   Plugin::registerClass('PluginFicheinterventionFicheintervention',
            array('addtabon' => array('Ticket')));

}
