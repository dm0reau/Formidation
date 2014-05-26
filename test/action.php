<html>
    <head>
        <title>Formidation test</title>
        <meta charset="utf-8">
    </head>
    <body>
        <?php
            include __DIR__.'/../Formidation.php';

            //Le constructeur 
            $f = new Formidation();

            //Définition de filtres et règles de validation
            $f->setRules('prenom', 'Prénom', 'required');
            $f->setRules('age', 'Âge', 'numeric|less_than[60]');
            $f->setFilters('nom', 'Nom', 'encode_php_tags');

            //Ajout d'une règle de validation à un champ déjà déclaré
            $f->addRule('nom', 'Nom', 'required');
            //Ajout d'une règle personnalisée à un champ déjà déclaré
            $f->addCustomRule('age', 'Âge', 'Tu dois être majeur !', function($name, $value) {
                if ($_POST[$name] < 18)
                    return false;
                return true;
            });

            //Ajout de filtres à un champ déclaré
            $f->addFilter('nom', 'Nom', 'trim');
            $f->addCustomFilter('nom', 'Nom', function($name, $value) {
                return str_replace('o', '0', $value);
            });

            if ($f->valid()) {
                echo '<h1>Formulaire valide</h1>';
                echo '<p>Nom : '.$_POST['nom'].'</p>';
                echo '<p>Prénom : '.$_POST['prenom'].'</p>';
                echo '<p>Âge : '.$_POST['age'].'</p>';
            } else {
                echo '<h1>Erreur(s)</h1>'.$f->getErrors();
            }
        ?>
    </body>
</html>
