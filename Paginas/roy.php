<!DOCTYPE HTML>
<html>	
	<head>
		<link rel="stylesheet" type="text/css" 	href="/estilos/estilo.css">
		<script type="text/javascript">
			function mostrar()
			{
		    	document.getElementById('oculto').style.display='block';
				document.getElementById('oculto').style.position='absolute';
				document.getElementById('oculto').style.top='0';
				document.getElementById('oculto').style.left='0';
				document.getElementById('oculto').style.zIndex='25';
				document.getElementById('oculto').style.width='100vw';
				document.getElementById('oculto').style.height='100vh';
			}
			
			function ocultar()
			{
		    	document.getElementById('oculto').style.display='none';
			}
		</script>
	</head>
	<body>
		<?php
			//Comprobamos que el usuario esta logueado
			session_start();
			if (!isset($_SESSION["usuario"]))
			{ 
				header("Location: /");
			}
			require_once ("Image/GraphViz.php");
			include ("./Nodo.php");
			require_once ("./funciones.php");
			//Cargamos el idioma
			require_once("../".idioma());
	
			//Comprobamos si se nos ha pasdo el id de un grafo ya resuelto.
			if(isset($_GET["id"]))
			{
				$idGrafo = $_GET["id"];		
				$conexion = conectarse();
				
				//Buscamos la informacion del grafo
				$consulta = "SELECT GRAFO from grafos WHERE ID_GRAFO = {$idGrafo} AND GRAFO IS NOT NULL";
				$result = $conexion->query($consulta);
				$tuplas = $result->num_rows;
				//Comprobamos que existen los datos
				if($tuplas != 0)
				{
					//Mostramos el grafo
					$reg = $result->fetch_assoc();
					echo "<div class=\"ampliable\" onClick=\"mostrar();\">{$reg["GRAFO"]}</div>";
					$grafo = preg_replace("/<svg width=\"\d+pt\" height=\"\d+pt\"/","<svg style=\"max-height: none;\" height=\"100%\"",$reg["GRAFO"]);
					echo "<div onClick=\"ocultar();\" id=\"oculto\" class=\"oculto\">".$grafo."</div>";
					mysqli_close($conexion);
					
					//Mostramos la evaluacion para este grafo
					evaluar($idGrafo);
				}
				//Si no existen los datos mostramos un error
				else
				{
					mysqli_close($conexion);
					header("Location: ../paginas/error.php?e=".urlencode($texto["Roy_1"]));
				}
			}
			//Si no tenemos id comprobamos que tenemos la tabla de precedencias
			else if(isset($_POST["nombres"]) && isset($_POST["precedencias"]) && isset($_POST["duraciones"]))
			{
				$nombres = $_POST["nombres"];
				$precedencias = $_POST["precedencias"];
				$duraciones = $_POST["duraciones"];
				$resolver = false;
				
				//Si necesitamos obtener las respuestas correctas a las preguntas (resolver) comprobamos que tambien tenemos las respuestas que ha dado el usuario.
				if ((isset($_POST["resolver"])) && (isset($_POST["pregunta1"])) && (isset($_POST["pregunta2"])) && (isset($_POST["pregunta3"])) && (isset($_POST["pregunta4"])) && (isset($_POST["pregunta5"])))
				{	
					$resolver = true;
					$conexion = conectarse();
					
					//Buscamos las respuestas del usuario.
					$consulta = "SELECT * FROM respuestas WHERE ID_GRAFO = (SELECT ID_GRAFO FROM grafos WHERE CALIFICACION IS NULL AND ID_USUARIO = {$_SESSION["id_usuario"]})";
					$result = $conexion->query($consulta);
					$tuplas = $result->num_rows;			
					//Si las respuestas no están almacenadas lo hacemos ahora.
					if($tuplas == 0)
					{
						$consulta = "INSERT INTO respuestas(ID_GRAFO, RESPUESTA_1, RESPUESTA_2, RESPUESTA_3, RESPUESTA_4, RESPUESTA_5) VALUES((SELECT ID_GRAFO FROM grafos WHERE CALIFICACION IS NULL AND ID_USUARIO = {$_SESSION["id_usuario"]}), {$_POST["pregunta1"]}, {$_POST["pregunta2"]}, {$_POST["pregunta3"]}, {$_POST["pregunta4"]}, UPPER(REPLACE('{$_POST["pregunta5"]}', ' ', '')))";
						$conexion->query($consulta);
					}
					
					//Buscamos las preguntas del grafo.
					$consulta = "SELECT * FROM preguntas WHERE ID_GRAFO = (SELECT ID_GRAFO FROM grafos WHERE CALIFICACION IS NULL AND ID_USUARIO = {$_SESSION["id_usuario"]})";
					$result = $conexion->query($consulta);
					$tuplas = $result->num_rows;
					if($tuplas != 0)
					{
						$preguntas = $result->fetch_assoc();
					}
					//Si no hay preguntas mostramos un error.
					else
					{
						mysqli_close($conexion);
						header("Location: ../paginas/error.php?e=".urlencode($texto["Roy_2"]));
					}
				}
				
				//////////////RESOLUCION ROY//////////////
			
				$grafo = array();
				
				/////Generamos el conjunto de nodos////
				for($i = 0; $i < count($nombres); $i++)
				{
					$grafo[$nombres[$i]] = new Nodo($nombres[$i], $duraciones[$i]);
					$precedencias[$i] = explode(" ", $precedencias[$i]);			
					foreach($precedencias[$i] as $value)
					{
						if($value != "")
						{
							$grafo[$nombres[$i]]->addNodoPrecedente($value);
						}
					}
				}
				
				//Establecemos las precedencias
				for($i = 0; $i < count($nombres); $i++)
				{
					foreach($precedencias[$i] as $value)
					{
						if($value != "")
						{
							$grafo[$value]->addNodoPosterior($nombres[$i]);
						}
					}
				}
				
				$inicio = new Nodo("Inicio", 0);
				$fin = new Nodo("Fin", 0);
				
				foreach($grafo as $value)
				{
					if(count($value->getPrecedentes()) == 0)
					{
						$inicio->addNodoPosterior($value->getID());
						$value->addNodoPrecedente($inicio->getID());
					}
					
					if(count($value->getPosteriores()) == 0)
					{
						$fin->addNodoPrecedente($value->getID());
						$value->addNodoPosterior($fin->getID());
					}
				}
				
				$grafo["Inicio"] = $inicio;
				$grafo["Fin"] = $fin;
				
				//Calculamos los tiempos early y late de inicio
				calcularTEI($grafo, $grafo["Inicio"]);
				foreach($grafo as $value)
				{
					$value->setTLI($grafo["Fin"]->getTEI());
				}	
				calcularTLI($grafo, $grafo["Fin"]);
				
				
				/////Generamos el grafo grapviz////
				$gv = new Image_GraphViz(true, array("rankdir"=>"LR", "size"=>"8.333,11.111!"), "ROY", false, false);
				
				//Añadimos los nodos al grafo
				foreach($grafo as $value)
				{
					//$gv->addNode($value->getID(), array("shape"=>"box"));
					$gv->addNode($value->getID(), array("shape"=>"box","label"=>"<TABLE border=\"0\"><TR><TD colspan=\"2\">{$value->getID()}</TD></TR><TR><TD>{$value->getTEI()}</TD><TD>{$value->getTLI()}</TD></TR><TR><TD colspan=\"2\">{$value->getDuracion()}</TD></TR></TABLE>"));
					//Si es necesario obtenemos la respuesta a la pregunta 4
					if(($value->getID() == "Fin") && $resolver)
					{
						$respuesta4 = $value->getTEI();
					}
				}
				
				$respuesta5 = "";
				//Añadimos los arcos
				foreach($grafo as $value)
				{
					foreach($value->getPrecedentes() as $p)
					{
						$color = "black";
						if(($value->getHolguraTotal() == 0) && ($grafo[$p]->getHolguraTotal() == 0))
						{
							$color = "red";
							
							if($value->getID() != "Fin")
							{
								//Si es necesario obtenemos la respuesta a la pregunta 5
								if(($respuesta5 != "") && $resolver)
								{
									$respuesta5 = $respuesta5.",";
								}
								if($resolver)
								{
									$respuesta5 = $respuesta5.$value->getID();
								}
							}
						}
						
						//Si es necesario obtenemos la respuesta a las pregunta 1 2 3
						if($resolver)
						{
							if($value->getID() == $preguntas["NOMBRE_1"])
							{
								$respuesta1 = $value->getHolguraTotal();
							}
							
							if($value->getID() == $preguntas["NOMBRE_2"])
							{
								$respuesta2 = $value->getTEI();
							}
							
							if($value->getID() == $preguntas["NOMBRE_3"])
							{
								$respuesta3 = $value->getTLI() + $value->getDuracion();
							}
						}
						
						$gv->addEdge(array($p => $value->getID()), array("color" => $color));
					}
				}
				
				//Si es necesario guardamos las respuestas correctas en la BD
				if($resolver)
				{
					$consulta = "INSERT INTO respuestas_correctas(ID_GRAFO, RESPUESTA_1, RESPUESTA_2, RESPUESTA_3, RESPUESTA_4, RESPUESTA_5) VALUES({$preguntas["ID_GRAFO"]}, {$respuesta1}, {$respuesta2}, {$respuesta3}, {$respuesta4}, '{$respuesta5}');";
					$conexion->query($consulta);
				}
				
				//Dibujamos el grafo
				$data = $gv->fetch();
				$data = substr($data, strpos($data, "<!--"));
				
				//Si es necesario guardamos el grafo en la BD
				if($resolver)
				{
					$consulta = "UPDATE grafos SET GRAFO = '{$data}' WHERE ID_GRAFO = {$preguntas["ID_GRAFO"]};";
					$conexion->query($consulta);
					mysqli_close($conexion);
				}
				
				//Mostramos el grafo
				echo "<div class=\"ampliable\" onClick=\"mostrar();\">{$data}</div>";
				$grafo = preg_replace("/<svg width=\"\d+pt\" height=\"\d+pt\"/","<svg style=\"max-height: none;\" height=\"100%\"",$data);
				echo "<div onClick=\"ocultar();\" id=\"oculto\" class=\"oculto\">".$grafo."</div>";
				
				//Mostramos las preguntas y respuestas correspondientes si es necesario
				if($resolver)
				{
					$idGrafo = $preguntas["ID_GRAFO"];
					evaluar($idGrafo);
				}
			}
			//Si no tenemos la tabla de precedencias mostramos un error.
			else
			{
				header("Location: ../paginas/error.php?e=".urlencode($texto["Roy_3"]));
			}
			
			/**
				  * Calcula los TEI para los nodos de un grafo
				  * @param grafo array de Nodo con que conforman el grafo
				  * @param n El nodo "INICIO" del grafo
				  */
				function calcularTEI($grafo, $n)
				{
					foreach($n->getPosteriores() as $value)
					{
						$grafo[$value]->setTEI(max($grafo[$value]->getTEI(), $n->getTEI() + $n->getDuracion()));
					}
					
					foreach($n->getPosteriores() as $value)
					{
						calcularTEI($grafo, $grafo[$value]);
					}
				}
				
				 /**
				  * Calcula los TLI para los nodos de un grafo
				  * @param grafo array de Nodo con que conforman el grafo
				  * @param n El nodo "FIN" del grafo
				  */
				function calcularTLI($grafo, $n)
				{
					//TLI = TLI(+1) - D(0)
					foreach($n->getPrecedentes() as $value)
					{
						$grafo[$value]->setTLI(min($grafo[$value]->getTLI(), $n->getTLI() - $grafo[$value]->getDuracion()));
					}
					
					foreach($n->getPrecedentes() as $value)
					{
						calcularTLI($grafo, $grafo[$value]);
					}
				}
		?>
	</body>
</html>