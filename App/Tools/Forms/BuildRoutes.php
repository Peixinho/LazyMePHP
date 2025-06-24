<?php

namespace Tools\Forms;
use Core\LazyMePHP;

require_once __DIR__ . '/../Database';
require_once __DIR__ . '/../Helper';

class BuildRoutes {
    
    public function __construct($routesPath, $db) {
        $this->constructRoutes($routesPath, $db);
    }

    public function constructRoutes($routesPath, $db) {
        $filePath = $routesPath . "/" . $db->GetTableName() . ".php";
        if (\Tools\Helper\UNLINK($filePath)) {
            if (\Tools\Helper\TOUCH($filePath)) {
                $routerFile = fopen($filePath, "w+");
                fwrite($routerFile, "<?php");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "/**");
                fwrite($routerFile, "\n");
                fwrite($routerFile, " * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
                fwrite($routerFile, "\n");
                fwrite($routerFile, " * @author Duarte Peixinho");
                fwrite($routerFile, "\n");
                fwrite($routerFile, " *");
                fwrite($routerFile, "\n");
                fwrite($routerFile, " * Source File Generated Automatically");
                fwrite($routerFile, "\n");
                fwrite($routerFile, " */");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "declare(strict_types=1);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "namespace Routes;\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "use Pecee\SimpleRouter\SimpleRouter;");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "use Controllers\\".$db->GetTableName().";");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "use Core\\LazyMePHP;");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "use Core\\Http\\Request;");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "require_once __DIR__ . '/../Core/BladeFactory.php';\n");
                fwrite($routerFile, "\$blade = \\Core\\BladeFactory::getBlade();\n\n");

                fwrite($routerFile, "/*");
                fwrite($routerFile, "\n");
                fwrite($routerFile, " * ".$db->GetTableName()." Routing");
                fwrite($routerFile, "\n");
                fwrite($routerFile, " */");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "SimpleRouter::get('/".$db->GetTableName()."', function() use (\$blade) : void {");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$request = new Request();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller = new ".$db->GetTableName()."(\$request);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$data = \$controller->index((int)(\$request->get('page')??1), LazyMePHP::NRESULTS());");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\techo \$blade->run(\"".$db->GetTableName().".index\", [");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\t'controller' => '".$db->GetTableName()."',");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\t'".$db->GetTableName()."' => \$data['".$db->GetTableName()."'], 'filters' => \$data['filters'], 'length' => \$data['length'], 'current' => \$request->get('page')??1, 'limit' => LazyMePHP::NRESULTS()");
                // Add foreign table data
                foreach ($db->GetTableFields() as $field) {
                    if ($field->HasForeignKey() && $field->GetForeignField()) {
                        fwrite($routerFile, ", '".$field->GetForeignTable()."' => \$data['".$field->GetForeignTable()."']");
                    }
                }
                fwrite($routerFile, "]);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "});");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "SimpleRouter::get('/".$db->GetTableName()."/{".$db->GetPrimaryFieldName()."}/edit', function (\$".$db->GetPrimaryFieldName().") use (\$blade) : void {");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$request = new Request();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller = new ".$db->GetTableName()."(\$request);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$data = \$controller->edit((int)\$".$db->GetPrimaryFieldName().");");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\techo \$blade->run(\"".$db->GetTableName().".edit\", [");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\t'controller' => '".$db->GetTableName()."',");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\t'".$db->GetTableName()."' => \$data['".$db->GetTableName()."']");
                // Add foreign table data
                foreach ($db->GetTableFields() as $field) {
                    if ($field->HasForeignKey() && !is_null($field->GetForeignField())) {
                        fwrite($routerFile, ",\n\t\t'".$field->GetForeignTable()."' => \$data['".$field->GetForeignTable()."']");
                    }
                }
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t]);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "});");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "SimpleRouter::get('/".$db->GetTableName()."/new', function () use (\$blade) : void{");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$request = new Request();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller = new ".$db->GetTableName()."(\$request);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$data = \$controller->edit();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\techo \$blade->run(\"".$db->GetTableName().".edit\", [");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\t'controller' => '".$db->GetTableName()."',");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\t'".$db->GetTableName()."' => \$data['".$db->GetTableName()."']");
                // Add foreign table data
                foreach ($db->GetTableFields() as $field) {
                    if ($field->HasForeignKey() && !is_null($field->GetForeignField())) {
                        fwrite($routerFile, ",\n\t\t'".$field->GetForeignTable()."' => \$data['".$field->GetForeignTable()."']");
                    }
                }
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t]);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "});");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "SimpleRouter::post('/".$db->GetTableName()."/{id}', function (\$id) : void {");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$request = new Request();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller = new ".$db->GetTableName()."(\$request);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller->save((int)\$id);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\\Core\\Helpers\\Helper::redirect('/".$db->GetTableName()."');");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "})->addMiddleware('\\Core\\Security\\CsrfMiddleware');");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "SimpleRouter::post('/".$db->GetTableName()."', function () : void {");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$request = new Request();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller = new ".$db->GetTableName()."(\$request);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller->save();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\\Core\\Helpers\\Helper::redirect('/".$db->GetTableName()."');");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "})->addMiddleware('\\Core\\Security\\CsrfMiddleware');");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "SimpleRouter::post('/".$db->GetTableName()."/{id}/delete', function (\$id) {");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$request = new Request();");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller = new ".$db->GetTableName()."(\$request);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\$controller->delete((int)\$id);");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\t\\Core\\Helpers\\Helper::redirect('/".$db->GetTableName()."');");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "})->addMiddleware('\\Core\\Security\\CsrfMiddleware');");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "\n");
                fwrite($routerFile, "?>");
                fclose($routerFile);
            } else {
                echo "ERROR: Check your permissions to write the router file on $filePath\n";
            }
        } else {
            echo "ERROR: Check your permissions to remove the router file on $filePath\n";
        }
    }
} 