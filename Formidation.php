<?php
	require_once __DIR__.'/inc/Field.php';

	/**
	 * Classe permettant de valider un ensemble de champs dans un formulaire, puis de retourner les erreurs appropriées.
	 * Son fonctionnement est inspiré de la librairie Form_validation de CodeIgniter : http://ellislab.com/codeigniter/user-guide/libraries/form_validation.html
	 * @require PHP >= 5.3
	 */ 
	class Formidation {
        //Chemins vers les répertoires utilisés par la librairie
        private static $rules_dir;
        private static $filters_dir;
        private static $lang_dir;

		//Langue courante pour les erreurs de validation
		private $lang;
        //Messages d'erreurs correspondant aux règles de validation
        private $rules_errors;
		//Erreurs générées de validation
		private $errors;
        //Code HTML affiché avant chaque erreur
        private $before_error;
        //Code HTML affiché après chaque erreur
        private $after_error;
		//Champs du formulaire
		private $fields;

		/**
		 * @param string $lang = 'fr' Langue à utiliser pour les erreurs générées.
         * @param array $rules_files = array('base') Fichiers de règles à inclure. Envoyer un tableau rempli de chaînes 
         * correspondant aux noms des fichiers sans l'extension .php
         * @param array $filters_files = array('base') Fichiers de filtres à inclure. Envoyer un tabeau rempli de chaînes
         * correspondant aux noms des fichiers fichiers sans l'extension .php
		 */
		public function __construct($lang = 'fr', array $rules_files = array('base'), array $filters_files = array('base')) {
            //Assignation des attributs
            self::$lang_dir = __DIR__.'/lang/';
            self::$rules_dir = __DIR__.'/rules/';
            self::$filters_dir = __DIR__.'/filters/';

			$this->lang = $lang;
			$this->errors = '';
            $this->before_error = '<p>';
            $this->after_error = '</p>';
			$this->fields = array();

            //Inclusion des librairies de règles de validation et fichiers de langue correspondants.
            $this->rules_errors = array();
            foreach ($rules_files as $rules_file) {
                require_once self::$rules_dir.$rules_file.'.php';
                $this->rules_errors = array_merge(
                    $this->rules_errors, 
                    include(self::$lang_dir.$rules_file.'_'.$this->lang.'.php')
                );
            }
            //Inclusion des librairies de filtres.
            foreach ($filters_files as $filters_file) {
                require_once self::$filters_dir.$filters_file.'.php';
            }
		}

        /**
         * Changer les balises entourant les messages d'erreur.
         * @param string $before
         * @param string $after
         */
        public function setErrorsDelimiters($before, $after) {
            $this->before_error = $before;
            $this->after_error = $after;
        }

		/**
		 * Validation des règles sur tous les champs renseignés.
		 * @return boolean
		 */
		public function valid() {
            //Valeur retournée
            $return = true;

			foreach ($this->fields as $field) {
                //Application des filtres
                if ($field->hasFilters()) {
                    $filters = $field->getFilters();
                    $filters = explode('|', $filters);
                    foreach ($filters as $filter) {
                        $param = false;
                        //Récupération du paramètre
                        if (substr($filter, -1) == ']') {
                            $param = $this->getStringBetween($filter, '[', ']');
                            $filter = substr($filter, 0, strpos($filter, '['));
                        }

                        if ( ! function_exists($filter)) {
                            throw new Exception('La fonction filtre "'.$filter.'" n\'a pas été déclarée. Peut-être un oubli
                                dans les inclusions ?');
                        }

                        if ($param) {
                            $newValue = $filter($field->getValue(), $param);
                            $field->setValue($newValue);
                        } else {
                            $newValue = $filter($field->getValue());
                            $field->setValue($newValue);
                        }
                    }
                }

                //Appel des filtres personnalisés
                if ($field->hasCustomFilters()) {
                    foreach ($field->getCustomFilters() as $custom_filter) {
                        $newValue = $custom_filter($field->getName(), $field->getValue());
                        $field->setValue($newValue);
                    }
                }
                
                //Vérification des règles de validation
                if ($field->hasRules()) {
                    $rules = $field->getRules();
                    //Parsing des règles
                    $rules = explode('|', $rules);
                    foreach ($rules as $rule) {
                        $param = false;
                        //Récupération du paramètre
                        if (substr($rule, -1) == ']') {
                            $param = $this->getStringBetween($rule, '[', ']');
                            $rule = substr($rule, 0, strpos($rule, '['));
                        }

                        if ( ! function_exists($rule)) {
                            throw new Exception('La fonction de validation "'.$rule.'" n\'a pas été déclarée. Peut-être 
                                un oubli dans l\'inclusion des règles ?');
                        }
                        
                        //Appel des règles définies dans les librairies
                        if ($param && ! $rule($field->getValue(), $param)) {
                            $return = false;
                            $error = sprintf($this->rules_errors[$rule], $field->getLabel(), $param);
                            $this->addError($error);
                        }
                        else if ( ! $param && ! $rule($field->getValue())) {
                            $return = false;
                            $error = sprintf($this->rules_errors[$rule], $field->getLabel());
                            $this->addError($error);
                        }
                    }
                }

                //Appel des règles personnalisées
                if ($field->hasCustomRules()) {
                    foreach ($field->getCustomRules() as $custom_rule) {
                        if ( ! $custom_rule['function']($field->getName(), $field->getValue())) {
                            $error = sprintf($custom_rule['error'], $field->getLabel());
                            $this->addError($error);
                            $return = false;
                        }
                    }
                }
			}

            return $return;
		}

        /**
         * Ajout d'une règle de validation existante à un champ. Celle-ci peut représenter une fonction définie dans les
         * librairies, ou une fonction PHP déjà présente par défaut.
         * @param string $name L'attribut name du champ.
         * @param string $label Label du champ concerné.
         * @param string $rule Nom de la règle.
         */
        public function addRule($name, $label, $rule) {
            if ( ! function_exists($rule)) {
                throw new Exception('La règle (fonction) que vous cherchez à ajouter n\'a pas été déclarée.');
            }
			$field = $this->getFieldByName($name);
			if ( ! $field) {
				$field = new Field($name, $label);
                $this->fields[$name] = $field;
            }
            $field->addRule($rule);
        }

		/**
		 * Ajout d'une règle de validation personnalisée à un champ. À utiliser si votre règle est bien particulière. 
         * Autrement, il est recommandé de créer votre règle dans une librairie de règles (présentes dans le dossier "rules").
		 * @param string $name L'attribut name du champ.
         * @param string $label Label du champ concerné.
		 * @param string $error Le message d'erreur utilisé si la règle n'est pas validée.
		 * @param Callable $rule Une fonction anonyme ayant deux paramètres (nom du champ et valeur du champ), retournant true ou false.
		 */ 
		public function addCustomRule($name, $label, $error, Callable $rule) {
			$field = $this->getFieldByName($name);
			if ( ! $field) {
				$field = new Field($name, $label);
                $this->fields[$name] = $field;
            }
            $field->addCustomRule($rule, $error);
		}

		/**
		 * Définition de règles de validation pour un champ donné.
		 * @param string $name Attribut name du champ concerné.
		 * @param string $label Label du champ concerné.
		 * @param string $rules Règles de validation à appliquer au champ concerné, séparées par des |. Exemple : 'required|greater_than[3]'
		 */
		public function setRules($name, $label, $rules) {
            $field = $this->getFieldByName($name);
            if ( ! $field) {
                $field = new Field($name, $label);
                $this->fields[$name] = $field;
            }
            $field->setRules($rules);
		}

        /**
         * Ajout d'un nouveau filtre à un champ.
		 * @param string $name Attribut name du champ concerné.
		 * @param string $label Label du champ concerné.
         * @param string $filter Nom du filtre.
         */
        public function addFilter($name, $label, $filter) {
            $field = $this->getFieldByName($name);
            if ( ! $field) {
                $field = new Field($name, $label);
                $this->fields[$name] = $field;
            }
            $field->addFilter($filter);
        }

		/**
		 * Ajout d'un filtre personnalisé à un champ. À utiliser si votre filtre est bien particulier.
         * Autrement, il est recommandé de créer votre filtre dans une librairie de filtres (présent dans le dossier "filters").
		 * @param string $name L'attribut name du champ.
         * @param string $label Label du champ concerné.
		 * @param Callable $filter Une fonction anonyme ayant deux paramètres (nom du champ et valeur du champ), retournant
         * la valeur du champ filtrée.
		 */ 
		public function addCustomFilter($name, $label, Callable $filter) {
			$field = $this->getFieldByName($name);
			if ( ! $field) {
				$field = new Field($name, $label);
                $this->fields[$name] = $field;
            }
            $field->addCustomFilter($filter);
		}

        /** 
         * Définition des filtres pour un champ donné.
         * @param string $name Attribut name du champ concerné.
         * @param string $label Label du champ concerné.
         * @param string $filters Filtres à appliquer au champ concerné, séparées par des |. Exemple : 'trim|prep_url'
         */
        public function setFilters($name, $label, $filters) {
            $field = $this->getFieldByName($name);
            if ( ! $field) {
                $field = new Field($name, $label);
                $this->fields[$name] = $field;
            }
            $field->setFilters($filters);
        }
		
        /**
         * Donne les messages d'erreur résultant d'une vérification des règles de validation.
         * @return string $errors
         */
        public function getErrors() {
            return $this->errors;
        }

		/**
		 * Donne un champ par son attribut name.
		 * @param string $name Attribut name du champ.
		 * @return Field $field OU false si le champ n'existe pas.
		 */
		public function getFieldByName($name) {
			if (isset($this->fields[$name])) {
				return $this->fields[$name];
            }

			return false;
		}

        /**
         * Ajoute l'erreur donnée aux erreurs de l'instance.
         * @param $error Erreur à rajouter.
         */
        private function addError($error) {
            $this->errors .= $this->before_error.$error.$this->after_error.PHP_EOL;
        }

		/**
		 * Donne une chaîne comprise entre deux paramètres donnés.
		 * @param string $string La chaîne à parser
		 * @param string $start Début avant la chaîne.
		 * @param string $end Fin avant la chaîne.
		 * @return string $parsed La chaîne parsée.
		 */
		private function getStringBetween($string, $start, $end) {
			 $string = " ".$string;
			 $ini = strpos($string, $start);
			 if ($ini == 0) {
			 	return "";
			 }
			 $ini += strlen($start);
			 $len = strpos($string, $end, $ini) - $ini;

			 return substr($string, $ini, $len);
		}
	}
