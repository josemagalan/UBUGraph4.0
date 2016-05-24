<?php
	require_once ("Image/GraphViz.php");
	require_once ("./Nodo.php");

	function generarNodos($nombres,&$precedencias,$duraciones){
		$grafo = array();
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
		return $grafo;
	}
	
	function establecerPrecedenciasRoy(&$grafo,$nombres,$duraciones, $precedencias){
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
	}
	/**
	  * Calcula los TEI para los nodos de un grafo
	  * @param grafo array de Nodo con que conforman el grafo
	  * @param n El nodo "INICIO" del grafo
	  */
	function calcularTEI(&$grafo, $n)
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
	function calcularTLI(&$grafo, $n)
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
	
	function calcularTiempos(&$grafo){
		calcularTEI($grafo, $grafo["Inicio"]);
		foreach($grafo as $value)
		{
			$value->setTLI($grafo["Fin"]->getTEI());
		}	
		calcularTLI($grafo, $grafo["Fin"]);
	}
	
	function generarGrafoRoy($grafo,$resolver=false,$conexion = null,$preguntas = null){
		$gv = new Image_GraphViz(true, array("rankdir"=>"LR", "size"=>"8.333,11.111!"), "ROY", false, false);
		
		//A�adimos los nodos al grafo
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
		//A�adimos los arcos
		foreach($grafo as $value)
		{
			foreach($value->getPrecedentes() as $p)
			{
				$color = "black";

				if(($value->getHolguraTotal() == 0) && ($grafo[$p]->getHolguraTotal() == 0))
				{
					$color = "red";
					$value->setCritico();
					$grafo[$p]->setCritico();
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
		return $gv;
	}
	
	function dibujarGrafo($gv){
		$data = $gv->fetch();
		$data = substr($data, strpos($data, "<!--"));
		return $data;
	}
	
	function generarTablaPrecedencias($numAct,$probabilidad, $ids, &$nombres, &$precedencias, &$duraciones){
		for($i = 0; $i < $numAct; $i++)
		{
			array_push($nombres, $ids[$i]);
			
			$p = "";
			for($j = 0; $j < $i; $j++)
			{
				if($j != $i)
				{
					if(rand(1,100) <= $probabilidad)
					{
						if($p == "")
						{
							$p = $nombres[$j];
						}
						else
						{
							$p = $p." ".$nombres[$j];
						}
					}
				}
			}
			
			array_push($precedencias, $p);
			
			$duracionNodo = rand(1,25);
			array_push($duraciones, $duracionNodo);
		}
	}
?>