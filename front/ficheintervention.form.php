<?php
require_once("../lib/dompdf/dompdf_config.inc.php");
include ('../../../inc/includes.php');

global $CFG_GLPI;

if (empty($_REQUEST['task_id'])) {
   echo "Error : No task selected";
   exit;
}

function strnpos($haystack, $needle, $occurance, $pos = 0) {
   for ($i = 1; $i <= $occurance; $i++) {
      $pos = strpos($haystack, $needle, $pos) + 1;
   }
   return $pos - 1;
}

/**
 *  truncate text with saving html structure and doesn't breaking last word
 *  code function from https://gist.github.com/antonzaytsev/1260890
 */
function truncate($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true) {
   if ($considerHtml) {

      // if the plain text is shorter than the maximum length, return the whole text
      if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
         return $text;
      }

      // splits all html-tags to scanable lines
      preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
      $total_length = strlen($ending);
      $open_tags = array();
      $truncate = '';
      foreach ($lines as $line_matchings) {
         // if there is any html-tag in this line, handle it and add it (uncounted) to the output
         if (!empty($line_matchings[1])) {
            // if it's an "empty element" with or without xhtml-conform closing slash
            if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
               // do nothing
               // if tag is a closing tag
            } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
               // delete tag from $open_tags list
               $pos = array_search($tag_matchings[1], $open_tags);
               if ($pos !== false) {
                  unset($open_tags[$pos]);
               }
               // if tag is an opening tag
            } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
               // add tag to the beginning of $open_tags list
               array_unshift($open_tags, strtolower($tag_matchings[1]));
            }
            // add html-tag to $truncate'd text
            $truncate .= $line_matchings[1];
         }
         // calculate the length of the plain text part of the line; handle entities as one character
         $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
         if ($total_length+$content_length> $length) {
            // the number of characters which are left
            $left = $length - $total_length;
            $entities_length = 0;
            // search for html entities
            if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
               // calculate the real length of all entities in the legal range
               foreach ($entities[0] as $entity) {
                  if ($entity[1]+1-$entities_length <= $left) {
                     $left--;
                     $entities_length += strlen($entity[0]);
                  } else {
                     // no more characters left
                     break;
                  }
               }
            }
            $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
            // maximum lenght is reached, so get off the loop
            break;
         } else {
            $truncate .= $line_matchings[2];
            $total_length += $content_length;
         }
         // if the maximum length is reached, get off the loop
         if($total_length>= $length) {
            break;
         }
      }
   } else {
      if (strlen($text) <= $length) {
         return $text;
      } else {
         $truncate = substr($text, 0, $length - strlen($ending));
      }
   }
   // if the words shouldn't be cut in the middle...
   if (!$exact) {
      // ...search the last occurance of a space...
      $spacepos = strrpos($truncate, ' ');
      if (isset($spacepos)) {
         // ...and cut the text in this position
         $truncate = substr($truncate, 0, $spacepos);
      }
   }
   // add the defined ending to the text
   $truncate .= $ending;
   if($considerHtml) {
      // close all unclosed html-tags
      foreach ($open_tags as $tag) {
         $truncate .= '</' . $tag . '>';
      }
   }
   return $truncate;
}

$id_ticket = $_REQUEST['ticket_id'];

// Please, don't choise a very big logo (for performance with Apache server)
$logo_url = "../pics/" . "logo_1.png";
$line_img_url = "../pics/" ."leftLine.png";
$pointer_img_url = "../pics/" ."pointer.png";
$pointer_img_url_s = "../pics/" ."pointer_s1.png";
$table_img = "../pics/" ."table.png";

//TODO : Encodage -> UTF-8 : http://pxd.me/dompdf/www/examples.php#samples
//TODO : put CSS in a other file ?
$html = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
   <style>
   h1 {color:blue; border-bottom: 5px solid blue; margin: 1px; /* text-decoration:underline; */}
   @page {
      margin-top: 0.5em;
      margin-bottom: 1.5em;
      margin-left: 1.2em;
      margin-right: 1.4em;
   }
   body {
      font-family: Arial, Helvetica, sans-serif;
   }
   img { display:block }
   .blue {color:blue;}
   .table-bold {font-weight: bold;}
   .bulle {
      padding: 7px 20px 7px 20px;
      //background: #eeeeee;
      border: 1px solid black;
      border-radius: 10px;
      width: 85%;
   }
   .mini {
      width: 50%;
   }
   .max {
      width: 100%;
   }
   .cadre {
      padding: 7px 20px 7px 20px;
      //background: #eeeeee;
      border: 1.5px solid black;
   }
   .input {
      border:1px dotted black;
   }
   .input-red {
      border:1px dotted red;
   }
   .inner {
      width: 50%;
      margin: 0 auto;
   }
   .forcustomer {
      text-align:center;
      color: white;
      background-color:blue;
      font-weight: bold;
   }
   .values {
    font-style: italic;
   }
   .fieldimportant {
      font-size: 14px;
   }
   .ocrzone {
      font-size: 15.5px;
   }
    .troubleTale .emptyCheckbox{
    margin-left:0px;
    }
   .emptyCheckbox{
   border:2px solid #243F5B;
   border-radius:2px;
   width:12px;
   height:12px;
   display:inline-block;
   }
   .rooflessCheckbox{
   border:2px solid #4A7EBB;
   border-radius:2px;
   border-top:0px;
   width:30px;
   height:12px;
   display:inline-block;
   }
   </style>
</head>
<body>';

foreach ($_REQUEST['task_id'] as $task_id => $value) {
   $last_task_id = $task_id;
}

foreach ($_REQUEST['task_id'] as $task_id => $value) {

   $fiche = new PluginFicheinterventionFicheIntervention();
   $fiche->pregenerateInterventionSheet($id_ticket, $task_id);

   $footer = $fiche->getFooter();

   foreach ($fiche->fields as $key => $value) {
      //if ($fiche->fields[$key] == NOT_AVAILABLE) {
      //   $fiche->fields[$key] = __("NOT AVAILABLE", 'ficheintervention');
      //}
      //$fiche->fields[$key] = "<span class='values'>$value</span>";
   }

   $fiche->fields["description"] = nl2br($fiche->fields["description"]);

   //init :
   $compteur = 0;
   $length = 0;

   foreach (explode("<br />", $fiche->fields["description"]) as $ligne) {
      if (strlen($ligne) < 110) {
         $compteur += 110;
      } else {
         $compteur += strlen($ligne);
      }
   }

   $nb_br_in_description = substr_count($fiche->fields["description"], "<br />");

   // troncate after the 6th <br />
   if ($nb_br_in_description > 6) {
      $nb = strnpos($fiche->fields["description"], "<br />", 6);
      if ($nb > 120) {
         $nb = $nb - 60;
      }
      $length = $nb;
   }
   // troncate after 600-660 chars
   if ($compteur > 660) {
      if ($length == 0) {
         $length = 660;
      } elseif ($length > 660) {
         $length = 660;
      }
   }

   $fiche->fields["description"] = str_replace("<br />", "<br>", $fiche->fields["description"]);

   // troncate and add " ..." for say 'it's troncate'
   if ($length != 0) {
      $fiche->fields["description"] = truncate($fiche->fields["description"], $length, '...', true, true);

      //$fiche->fields["description"] .= " ...";
   }

   // add <br> is the only solution for have the same size
   //$nb_br_in_description = substr_count($fiche->fields["description"], "<br>");
   //if ($nb_br_in_description <= 6) {
   //$fiche->fields["description"] .= str_repeat("<br>", (6 - $nb_br_in_description));
   //}

   $numticket = $fiche->fields['ticket']."&nbsp;&nbsp;&nbsp;".$fiche->fields['task_date'];

//   echo '<pre>';
//   var_export($fiche);
//   exit;
   $html .= "
   <table  style='width:100%;'>
   <tr style='height:26px;'>
    <td style='vertical-align: top;padding:0px;width: 252px;'>
     <div style='background-image:url($line_img_url);background-size:cover;width: 255px;margin-top:10px;height:31px;margin-left:2px;'>&nbsp; </div>
     <div style='padding-left:8px;padding-top:10px;'>
     <div style='font-size:20px;text-decoration: underline;text-align: center;height:23px;'>Helpdesk contacto:</div>
     <div style='font-size:24px;text-align: center;font-style:italic;height:24px;'>932.896.199</div>
     <div style='font-size:18px;text-align: center;font-style:italic;'><a href='mailto:helpdesk@rcsangola.com'>helpdesk@rcsangola.com</a></div>
     </div>
     </td>
    <td style='vertical-align: top; color:#243F5B;padding-top:2px;padding-left:25px;font-size: 31px;font-weight: bold;'>
    <div>Ficha de intervenção</div>
    <table style='font-size:15px; width:350px; margin-left:-15px;margin-top:15px;text-align:center;' cellspacing='0' cellpadding='0'>
    <tr><td style='border-left:2px solid #243F5B;border-top:2px solid #243F5B;height:24px;'>Ticket N°</td>
    <td style='border-left:2px solid #243F5B;border-top:2px solid #243F5B;'>Date</td>
    <td style='border-left:2px solid #243F5B;border-top:2px solid #243F5B;border-right:2px solid #243F5B;'>Código Projecto</td></tr>
    <tr style=''><td style='border-left:2px solid #243F5B;border-top:2px solid #243F5B;border-bottom:2px solid #243F5B;height:36px;'>".$fiche->fields['ticket']."</td>
    <td style='border-left:2px solid #243F5B;border-top:2px solid #243F5B;border-bottom:2px solid #243F5B;'>".$fiche->fields['task_date']."</td>
    <td style='border-left:2px solid #243F5B;border-top:2px solid #243F5B;border-right:2px solid #243F5B;border-bottom:2px solid #243F5B;'>".$fiche->fields['origin']."</td></tr>
    </table></td>
    <td style='vertical-align: top;padding:0px;padding-top:9px;width: 112px;'> <div style='background-image:url($logo_url);background-size:cover;width:112px;height:110px;background-repeat:no-repeat;'>&nbsp; </div></td></tr>
</table>
<table style='width:100%;'>
   <tr> <td style=''>
   <div style='background:white;position:absolute;margin-left:294px;margin-top:-4px;height:14px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Informações Gerais </div>
   <div style='border:2px solid #243F5B;margin-top:2px;padding-top:3px;padding-left:24px;padding-right:30px;'>

   <table style='font-size:14px;margin-top:8px;' cellspacing='3'>
   <tr><td style='width:70px;font-weight:bold;font-style:italic;'>Técnico:</td><td style='width:198px;'>".$fiche->fields['technician']."</td><td style='font-weight:bold;width:80px;'> Piquete: <span class='emptyCheckbox'/></td><td style='width:70px;font-weight:bold;font-style:italic;''>Cliente: </td><td style=''>".$fiche->fields['requester']."</td></tr>
   <tr><td style='font-weight:bold;font-style:italic;'>Ajudante:</td><td colspan='2'></td><td style='font-weight:bold;font-style:italic;'>Telefone: </td><td>".$fiche->fields['requesterPhone']."</td></tr>
   <tr><td style='font-weight:bold;font-style:italic;'>Motorista:</td><td style='text-align:right;padding-right:20px;font-weight:bold;'>Matricula:</td><td></td><td style='font-weight:bold;font-style:italic;'>Contacto: </td><td>".$fiche->fields['requesterName']."</td></tr>
   <tr><td style='font-weight:bold;font-style:italic;'>Local:</td><td style='' colspan='2'>".$fiche->fields['targetItemLocation']."</td><td style='font-weight:bold;font-style:italic;'>Sala: </td><td></td></tr>
   </table>
   </div>
    </td> </tr> </table>

    <div style='padding-top:2px;'>
     <table  style='width:100%;' cellspacing='3'>
    <tr>
    <td style='border:2px solid #243F5B;width:50%;'>
    <div style='background:white;position:absolute;margin-left:134px;margin-top:-10px;height:17px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Equipamento </div>
    <div style='margin-top:2px;padding-top:3px;padding-left:16px;'>
   <table style='font-size:14px;width:100%;' cellspacing='8'>
   <tr><td style='font-style:italic;'width:150px;'><strong>Nome: </strong>".$fiche->fields["nameAsset"]."</td></tr>
   <tr><td><span style='font-style:italic;'><strong>ID: </strong></span><span>".$fiche->fields["idAsset"]."</span>&nbsp;&nbsp;&nbsp;&nbsp;<span style='font-weight:bold;font-style:italic;width:50px;'>Contrato: </span><span> ".$fiche->fields['num_contract']."</td></tr>
   <tr><td><span style='font-style:italic;'><strong>S/N:</strong> ".$fiche->fields['serial']."</span>&nbsp;&nbsp;&nbsp;&nbsp;
   <span style='font-style:italic;width:50px;'><strong>Tipo: </strong>".$fiche->fields['type']."</span></td></tr>
   <tr><td style='font-style:italic;'><strong>Modelo: </strong>".$fiche->fields['model']."</td></tr>
   </table>
   </div>
    </td>
    <td style='border:2px solid #243F5B;width:50%;'>
    <div style='background:white;position:absolute;margin-left:136px;margin-top:-10px;height:17px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Durações </div>
    <div style='margin-top:2px;padding-top:3px;padding-right:5px;'>
   <table style='font-size:10px;width:100%;' cellspacing='4'>
   <tr><td style='width:165px;'><span style='font-weight:bold;font-style:italic;'>Klm: </span> <span>".$fiche->fields['distancia']."</span></td> <td> <span style='font-weight:bold;font-style:italic;'>Agencia: </span><span>".$fiche->fields['agencia']."</span></td></tr>
   <tr><td><span style='font-weight:bold;font-style:italic;'>Data Intervenção:</span> </td> <td><span style='font-weight:bold;font-style:italic;'>Cliente Anterior:</span></td></tr>
   <tr><td><span style='font-weight:bold;font-style:italic;'>Saida anterior:</span> </td> <td> <span style='font-weight:bold;font-style:italic;'>Chegada posterior:</span></td></tr>
   <tr><td><span style='font-weight:bold;font-style:italic;'>Chegada cliente:</span> </td> <td><span style='font-weight:bold;font-style:italic;'>Saída cliente:</span> </td></tr>
   <tr><td  colspan='2' style='font-size:13px;padding-top:10px;'><span>Transporte <span class='emptyCheckbox'/></span>&nbsp;&nbsp;<span>Motorista <span class='emptyCheckbox'/></span>&nbsp;&nbsp;<span>Veiculo próprio  <span class='emptyCheckbox'/></span>&nbsp;&nbsp;<span>Remota  <span class='emptyCheckbox'/></span></td></tr>
   </table>
   </div>
   </td>
    </tr>
   </table></div>
   <table style='font-size:14px;width:100%;'><tr><td style='width:80%'>
   <div style='background:white;position:absolute;margin-left:200px;margin-top:-6px;height:15px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Descrição Problema </div>
   <div style='font-size:12px;border:2px solid #243F5B;margin-top:2px;height:104px;padding-top:4px;padding-left:25px;padding-right:10px;overflow:hidden;word-wrap: break-word;'>".$fiche->fields["title_ticket"].
       " / ".$fiche->fields["description"]."</div>
   </td><td style='width:20%;font-size:13px;'>
   <div style='background:white;position:absolute;margin-left:30px;margin-top:-6px;height:17px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Origem avaria </div>
   <div style='border:2px solid #243F5B;margin-top:2px;height:98px;padding-top:10px;padding-right:0px;'>
   <table class='troubleTale' style='text-align:right;width:100%;'>
   <tr><td style=''>Regular:</td><td style='width:17px;'><span class='emptyCheckbox'/></td></tr>
   <tr><td>Problema eléctrico:</td><td><span class='emptyCheckbox'/></td></tr>
   <tr><td>Preventiva:</td><td><span class='emptyCheckbox'/></td></tr>
   <tr><td>Uso inadequado:</td><td><span class='emptyCheckbox'/></td></tr>
   </table>
    </div>
   </td></tr>
   </table>
   <table style='font-size:14px;width:100%;'><tr><td>
   <div style='background:white;position:absolute;margin-left:294px;margin-top:-6px;height:17px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Descrição Intervenção </div>
   <div style='border:2px solid #243F5B;margin-top:2px;height:245px;padding-top:3px;padding-left:30px;padding-right:30px;background-image:url($pointer_img_url);'>
   </td></tr></table>

   <table style='font-size:14px;width:100%;'><tr><td style='width:70%'>
   <div style='background:white;position:absolute;margin-left:200px;margin-top:-4px;height:15px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Observações Cliente </div>
   <div style='border:2px solid #243F5B;margin-top:2px;height:91px;padding-top:10px;padding-left:30px;padding-right:30px;background-image:url($pointer_img_url_s) contain;'> </div>
   </td><td style='width:30%;font-size:13px;'>
   <div style='border:2px solid #243F5B;margin-top:2px;height:98px;padding-top:3px;padding-right:1px;'>
    <table style='text-align:right;width:100%;'>
   <tr><td>Trabalho resolvido:</td><td style='width:50px;padding-left:3px;'><span class='emptyCheckbox'/><span style='margin-left:8px;' class='emptyCheckbox'/> <div style='text-align:left;font-size:10px;padding-top:1px;'>Sim<span style='margin-left:3px;'>&nbsp;</span>Não</div> </td></tr>
   <tr><td>Comportamento técnico:</td><td style='padding-left:5px;'><span class='rooflessCheckbox'/></td></tr>
   <tr><td style='padding-top:5px;'>Satisfação Geral:</td><td style='padding-left:5px;'><span class='rooflessCheckbox'/></td></tr>
   <tr><td colspan='2' style='font-size:10px;text-align:right;'>(1 muito insatisfeito / 5 muito satisfeito)</td></tr>
   </table>
       </div>
   </td></tr>
   </table>

   <table style='font-size:14px;width:100%;'><tr>
   <td style='width:70%font-size:13px;'>
   <div style='background:white;position:absolute;margin-left:200px;margin-top:-6px;height:17px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'> Peças necessarias </div>
   <div style='border:2px solid #243F5B;margin-top:2px;height:115px;padding:0px;padding-top:7px;padding-bottom:0px;'>
   <div style='width:514px;height:108px;background-image:url($table_img);background-repat:no-repeat;'>
   <div><span style='display:inline-block;text-align:center;padding-top:2px;width:105px;'>Part Number</span>
   <span style='display:inline-block;text-align:center;padding-top:2px;width:125px;'>Descrição</span>
   <span style='display:inline-block;text-align:center;padding-top:2px;width:135px;'>Qde</span>
   <span style='display:inline-block;text-align:center;padding-top:2px;width:145px;'>E/R/C</span></div>
   </div>
   </div>
   </td><td style='width:30%;font-size:13px;'>
   <div style='background:white;position:absolute;margin-left:60px;margin-top:-6px;height:17px;color:#254061;font-size:14px;font-style:italic;padding-left:2px;padding-right:2px;'>Assinatura </div>
   <div style='border:2px solid #243F5B;margin-top:2px;height:110px;padding:2px;padding-top:10px;'></div>
   </td></tr>
   </table>
   <div style='font-size:15px;font-style:italic;'>Contacto Helpdesk Geral : 932.896.199  –  <a href='mailto:helpdesk@rcsangola.com'>helpdesk@rcsangola.com</a>  –  Rua da Liberdade n°94 Luanda Angola </div>
   ";
   // $html .= "<br><span class='blue' style='font-size: 11px;'>" . $footer . "</span>";

   if ($task_id != $last_task_id) {
      $html .= "<br><div style='page-break-before: always;'></div>";
   }
}
/*
$html .= "<script type='text/javascript'>
	try {
		this.print();
	}
	catch(e) {
		window.onload = window.print;
	}
</script>";*/

$html .= "</body></html>";

$file_name = _n("Intervention sheet", "Intervention sheets", 2, 'ficheintervention').
    " ".__("Ticket", 'ficheintervention')." ".$id_ticket.".pdf";

$dompdf = new DOMPDF();
$dompdf->load_html($html,'UTF-8');
$dompdf->set_paper('A4', 'portrait');
$dompdf->render();
$canvas = $dompdf->get_canvas();
$pages = $dompdf->get_canvas()->get_page_count();
$font = Font_Metrics::get_font("helvetica", "bold");
if($pages>1)
$canvas->page_text(290, 820, "{PAGE_NUM} of {PAGE_COUNT}", $font, 10, array(0,0,0));

$dompdf->stream($file_name, array('compress' => 1, 'Attachment' => 0));
