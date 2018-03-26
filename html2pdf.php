<?php
$path =	getcwd();
$path .= "./font/";
$path = realpath ($path);
define('FPDF_FONTPATH', $path);
require("fpdf.php");

#@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
#                                                   @
# class PDF extends FPDF                            @
#                                                   @
#@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

class PDF extends FPDF # doco at http://fpdf.org/
	{
	public $orient;
	public $currentY;
	public $bottomMargin;
	public $rightMargin;
	
  #####################################################
	#                                                   #
	# AcceptPageBreak                                   #
	#                                                   #
	#####################################################
  
  function AcceptPageBreak()
	{
  return false; # original useless FPDF function we don't want to use
	}

  #####################################################
	#                                                   #
	# CheckPageBreak                                    #
	#                                                   #
	#####################################################
  
  function CheckPageBreak($delta=0)
	{
	if ($this->getY() + $delta > $this->bottomMargin)
	 	{
		$this->AddPage($this->orient);
		$this->currentY = 10;
  	}

	$this->setXY(10, $this->currentY);
	return $this->currentY;
	}

  #####################################################
	#                                                   #
	# LoadData                                          #
	#                                                   #
	#####################################################
  
	function LoadData($input)
	{
  $input = preg_replace("/_/", " ", $input);
	$data=array();
	
	$lines = preg_split( '/\n/', $input);
	
	foreach($lines as $line)
	    $data[]=explode("\t",chop($line));
	return $data;
	}

  #####################################################
	#                                                   #
	# QuickTable                                        #
	#                                                   #
	#####################################################

	function QuickTable($widths, $alignments, $lines, $rowHeight, $percentWidth=false, $doBorders=false)
	{
	$ncols  = count ($widths);
	$data   = $this->LoadData($lines);
	$startX = $this->GetX();
	$startY = $this->GetY();
	$colStart = array();
	
  if ($percentWidth == true)
		{
		for ($i=0; $i < $ncols; $i++)
	  	$widths[$i] *= $this->rightMargin/100;
		}

	for ($i=0; $i < $ncols; $i++)
		{
		if ($i == 0)
			$colStart[$i] = $startX;
		else
			$colStart[$i] = $colStart[$i-1] + $widths[$i-1];
		}
	
	$lowestY = $this->currentY = $startY;
	
	foreach($data as $cols) # iterate through table rows
		{
		$lowestY = $this->CheckPageBreak();
	
		for ($i=0; $i < $ncols; $i++) 
			{
			if (substr($cols[$i], 0, 1)==="@") # hard coded page break
				{
				$cols[$i] = substr($cols[$i], 1);
				$this->AddPage();
				$this->setXY(10, 10);
				$this->currentY = 10;
				$lowestY = $this->currentY;
				}
			}
						
		for ($i=0; $i < $ncols; $i++) #iterate through row columns
		  {
	  	$this->SetXY($colStart[$i], $this->currentY);
		  			  
		  $this->MultiCell($widths[$i], $rowHeight, preg_replace("/`/", "\n", $cols[$i]), 0, $alignments[$i]);
			$lowestY = monitorHeight($lowestY, $this->getY()); # each cell can grow in height to fit text
			}
			
		if ($doBorders == true)
			{
			for ($i=0; $i < $ncols; $i++)
				$this->Rect($colStart[$i], $this->currentY, $widths[$i], $lowestY - $this->currentY);
			}

		$this->currentY = $lowestY; # we pick the tallest cell to move to next row
		}		
	}

  #####################################################
	#                                                   #
	# DoTableBorder                                     #
	#                                                   #
	#####################################################

	function DoTableBorder($x, $y, $x2, $y1, $y2, $divider)
	{
	if ($y1 > $y2)
		$bottom = $y1;
	else
		$bottom = $y2;
		
	$this->Rect($x, $y, $x2 - $x, $bottom - $y);
	$this->Line($divider, $y, $divider, $bottom);
	
	$this->currentY = $bottom;
	}		
		
} # end of class

#@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
#                                                   @
# class PDF2 extends PDF                            @
#                                                   @
#@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

class PDF2 extends PDF 
	{
#	var $firstImage;

  var $_numbering=false;
  var $_numberingFooter=false;
  var $_numPageNum=3; # cover + TOC

	var $outlines=array();
	var $OutlineRoot;
	var $fp = NULL; // debug log

	var $B;
	var $I;
	var $U;
	var $HREF;
	var $CENTER;
	var $inTable;
	
  #####################################################
	#                                                   #
	# PDF2                                              #
	#                                                   #
	#####################################################

	#####################################################
	#                                                   #
	# constructor                                       #
	#                                                   #
	#####################################################
		
  public function __construct($orientation='P', $unit='mm', $size='A4')
#	function PDF2($orientation='P', $unit='mm', $size='A4')
	{
	parent::__construct($orientation, $unit, $size);
# 	parent::FPDF($orientation, $unit, $size);

#	$this->fp = fopen ("pdfDebug.txt", "wb");
	$this->SetAutoPageBreak (true, 10);
	
	# in Cell we find - if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
	}
	
	#####################################################
	#                                                   #
	# destructor                                        #
	#                                                   #
	#####################################################
	
  public function __destruct() 
	{
#	fprintf ($this->fp, "wPt=%s\nhPt%s\nw=%s\nh=%s\nlMargin=%s\ntMargin=%s\nrMargin=%s\nbMargin=%s\n", $this->wPt,$this->hPt,$this->w,$this->h,$this->lMargin,$this->tMargin,$this->rMargin,$this->bMargin);
/*
var $wPt, $hPt;          // dimensions of current page in points
var $w, $h;              // dimensions of current page in user unit
var $lMargin;            // left margin
var $tMargin;            // top margin
var $rMargin;            // right margin
var $bMargin;            // page break margin
*/
	if (!is_null($this->fp))
		{
		fwrite ($this->fp, "\n=========================\nClosing debug log\n");
		fclose ($this->fp);
	  }
  }

  #####################################################
	#                                                   #
	# AcceptPageBreak                                   #
	#                                                   #
	#####################################################
  
  function AcceptPageBreak()
	{
  if ($this->inTable)
	  return false; 
  else
  	return true; 
	}
  
  #####################################################
	#                                                   #
	# WriteTag                                          #
	#                                                   #
	#####################################################

	function WriteTag ($w, $h, $txt, $border=0, $align="J", $fill=false, $padding=1, $maxY=-1)
	{
	$txt = preg_replace ("@<br\s*/*>@", "\n", $txt); 
		
	$this->leftTab  = $this->GetX();
	$this->rightTab = $this->leftTab + $w;
	
	$backlMargin = $this->lMargin;
	$backrMargin = $this->rMargin;
	$this->SetLeftMargin ($this->leftTab); 
	$this->SetRightMargin($this->w - $this->rightTab); 
	$this->topTab = $this->GetY();
			
	$a = preg_split('/<(.*)>/U', $txt, -1, PREG_SPLIT_DELIM_CAPTURE);
	$reachedVlimit = false;
	$retval = '';
	
	$wtDebug = false;
		
	if (!is_null($this->fp))
		{
		$wtDebug = true;
		fwrite ($this->fp, "*** WriteTag()\n");
		ob_start();
		var_dump ($a);
		$buf = ob_get_clean();
		fwrite ($this->fp, "var_dump (a)\n$buf\n");
		}
		
	if ($wtDebug)
		$this->SetTextColor(255,0,0);

	foreach ($a as $i => $e)
		{
		if ($reachedVlimit == true)
			{
			for ($j = $i; $j < count ($a); $j++)
				{						
	  		if	($j % 2 == 0)
					$retval .= $a[$j];
				else
					$retval .= "<{$a[$j]}>";
				}
			break;
			}

	  if ($i % 2 == 0) // Text
	    {        
			if ($this->HREF)
			  {
			  $this->SetTextColor(0,0,255);
			  $this->SetStyle('U',true);
			  }
			
			if ($align !== "L")
				{
				$lines = explode ("\n", $e);
				foreach ($lines as $line)
					{
					if (trim($line)!== '')
						$this->Cell ($w, $h, $line, 0, 2, $align, false, $this->HREF);
					}
				}
			else
				$retval = $this->MyWrite($h, $e, $this->HREF, $maxY, $align);
	
			if ($this->HREF)
				{
				$this->SetStyle('U',false);
				if ($wtDebug)
				  $this->SetTextColor(255,0,0);
				else
					$this->SetTextColor(0);
				
				if ($retval != '') 
					{
					$retval = "<a href=\"{$this->HREF}\">$retval</a>";
					$this->HREF = '';
					}
				}
			
			if ($retval != '') 
				{
				$reachedVlimit = true;
			#	fwrite ($this->fp, "*** WriteTag() stops at index i=$i\n");
	    	}
	    }
	    			
	  else // Tag
	    {        
      if ($e[0] == '/')
        $this->LeaveTag(strtoupper(substr($e,1)));
      
      else
      	{
        // Extract attributes
        $a2 = explode(' ',$e);
        $tag = strtoupper(array_shift($a2));
        $attr = array();
        
        foreach($a2 as $v)
          {
          if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
            $attr[strtoupper($a3[1])] = $a3[2];
          }
          
        $this->EnterTag($tag,$attr);
      	}
			}
		}

	if ($reachedVlimit == true)
		$this->SetY($maxY);
	
	else if ($align === 'L')
		$this->SetY($this->GetY() + $h);

	if (($border == 1) or ($wtDebug == true))
		{
		if ($reachedVlimit == true)
			$this->Rect($this->leftTab, $this->topTab, $w, $maxY -  $this->topTab, "D"); 
		else
			$this->Rect($this->leftTab, $this->topTab, $w, $this->GetY() -  $this->topTab, "D"); 
		}
	
	if ($wtDebug == true)
		$this->SetTextColor(0);
	
	$this->lMargin = $backlMargin;
	$this->rMargin = $backrMargin;

	if (!is_null($this->fp))
		fwrite ($this->fp, "*** WriteTag() returns\n$retval\n");
	
	return $retval; # leftover html that wasn't printed
	}

  #####################################################
	#                                                   #
	# EnterTag                                          #
	#                                                   #
	#####################################################

	function EnterTag ($tag, $attr)
	{
	$tag = strtoupper ($tag);

	switch ($tag)
		{
		case 'H2':
			$tag = 'B';

		case 'B':
		case 'I':
		case 'U':
			$this->SetStyle($tag, true);
			break;
		
		case 'A':
			$this->HREF=$attr['HREF'];
			break;		
		}
	}

  #####################################################
	#                                                   #
	# LeaveTag                                          #
	#                                                   #
	#####################################################

	function LeaveTag ($tag)
	{
	$tag = strtoupper ($tag);

	switch ($tag)
		{
		case 'H2':
			$tag = 'B';

		case 'B': 
		case 'I': 
		case 'U': 
			$this->SetStyle($tag, false);
			break;

		case 'A': 
			$this->HREF='';
			break;	
		}
	}

  #####################################################
	#                                                   #
	# SetStyle                                          #
	#                                                   #
	#####################################################

	function SetStyle($tag, $enable)
	{	
	$this->$tag += ($enable ? 1 : -1);
	$style = '';
	
	foreach(array('B', 'I', 'U') as $s)
		{
	  if ($this->$s > 0)
	    $style .= $s;
		}
		
	$this->SetFont('',$style);
	}
  
  #####################################################
	#                                                   #
	# MyWrite                                           #
	#                                                   #
	#####################################################

	function MyWrite ($h, $txt, $link='', $maxY=-1, $align='L', $fill=false) # 18 Oct 2016
	{
	// Output text in flowing mode
	$cw = &$this->CurrentFont['cw'];
	$w = $this->w-$this->rMargin-$this->x;
	$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
	$s = str_replace("\r",'',$txt);
	$nb = strlen($s);
	$sep = -1; # spot for line wrapping
	$i = 0;    # pointer to end of line
	$j = 0;    # pointer to start of line
	$l = 0;    # length of galley
	$nl = 1;   # number of lines
	
	$myLines = array();
	$retval = '';
		
	if (($maxY > 0) and ( $this->GetY() >= $maxY))
		{
		if (!is_null($this->fp))
			fwrite ($this->fp, "*** MyWrite(".substr($txt, 0, 30).") exit 0\nreturns ".substr($s, 0, 30)."\n");
		return "\n".$s;
		}
		
	while ($i < $nb)
		{
		// Get next character
		$c = $s[$i];
		
		if ($c == "\n")
			{
			if (($maxY > 0) and ( $this->GetY() >= $maxY) and ($i != $nb-1))
				{
				$retval = substr ($s, $j); 

				if (!is_null($this->fp))
					{
					fwrite ($this->fp, "*** MyWrite(".substr($txt, 0, 30).") align=$align exit 1\nreturns ".substr($retval, 0, 30)."\n");
					ob_start();
					var_dump ($myLines);
					$buf = ob_get_clean();
					fwrite ($this->fp, "var_dump (myLines)\n$buf\n");
					fwrite ($this->fp, "Next to print >".substr($s,$j, $i-$j)."< i=$i j=$j nb=$nb nl=$nl\n");
					}
					
				return $retval;
				}

			// Explicit line break
			$this->Cell($w,$h,substr($s,$j, $i-$j),0,2, $align,$fill,$link);
			$myLines[$nl-1] =   substr($s,$j, $i-$j);
			
			$i++;
			$sep = -1;
			$j = $i;
			$l = 0;
			
			if ($nl == 1)
				{
				$this->x = $this->lMargin;
				$w = $this->w-$this->rMargin-$this->x;
				$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
				}
			
			$nl++;
			continue;
			}
			
		if ($c == ' ')
			$sep = $i;
		
		$l += $cw[$c];
		
		if ($l > $wmax)
			{
 			// Automatic line break
			if ($sep == -1)
				{
				if ($this->x > $this->lMargin)
					{
					// Move to next line
					$this->x = $this->lMargin;
					$this->y += $h; 
						
					$w = $this->w-$this->rMargin-$this->x;
					$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
					$i++;
					$nl++;
					continue;
					}
					
				if ($i == $j)
					$i++;
					
				if (($maxY > 0) and ( $this->GetY() >= $maxY))
					{
					$retval = substr ($s, $j); 
					if (!is_null($this->fp))
						{
						fwrite ($this->fp, "*** MyWrite(".substr($txt, 0, 30).") exit 2\nreturns ".substr($retval, 0, 30)."\n");
						ob_start();
						var_dump ($myLines);
						$buf = ob_get_clean();
						fwrite ($this->fp, "var_dump (myLines)\n$buf\n");
						}
					return $retval;
					}

				$this->Cell($w,$h,substr($s,$j,$i-$j),0,2,$align,$fill,$link);
				$myLines[$nl-1] =   substr($s,$j,$i-$j);
				}
				
			else
				{
				if (($maxY > 0) and ( $this->GetY() >= $maxY))
					{
					$retval = substr ($s, $j); 
					if (!is_null($this->fp))
						{
						fwrite ($this->fp, "*** MyWrite(".substr($txt, 0, 30).") exit 3\nreturns ".substr($retval, 0, 30)."\n");
						ob_start();
						var_dump ($myLines);
						$buf = ob_get_clean();
						fwrite ($this->fp, "var_dump (myLines)\n$buf\n");
						}
					return $retval;
					}

				$this->Cell($w,$h,substr($s,$j,$sep-$j),0,2,$align,$fill,$link);
				$myLines[$nl-1] =   substr($s,$j,$sep-$j);
				$i = $sep+1;
				}
				
			$sep = -1;
			$j = $i;
			$l = 0;
			if ($nl == 1)
				{
				$this->x = $this->lMargin;
				$w = $this->w-$this->rMargin-$this->x;
				$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
				}
			$nl++;
			}
		else
			$i++;
		}

	// Last chunk
	if ($i != $j) 
		{
		if (($maxY > 0) and ( $this->GetY() >= $maxY))
			$retval = substr ($s, $j); 
		else
			$this->Cell($l/1000*$this->FontSize,$h,substr($s,$j),0,0,'',$fill,$link);

		if (!is_null($this->fp))
			fwrite ($this->fp, "*** Last chunk MyWrite(".substr($s, $j).")\nreturns ".substr($retval, 0, 30)."\nGetY()=".$this->GetY()."maxY=$maxY\n");
		}
		
	return $retval;
	}

  #####################################################
	#                                                   #
	# WriteHTML                                         #
	#                                                   #
	#####################################################

	function WriteHTML ($html)
	{
	$html = preg_replace("@<!--.*-->@smU", "", $html);
		
	$html = preg_replace("/\r/", "", $html);

	$html = preg_replace("/\&amp;/", "&", $html);
	$html = preg_replace("/\&nbsp;/", " ", $html); # 18 Oct 2016
	$html = preg_replace ("@<br\s*/*>\n@smU", "\n", $html); 
	$html = preg_replace ("@<br\s*/*>@smU", "\n", $html); 

	$html = preg_replace("@<li>@i", "* ", $html);
	$html = preg_replace("@</li>@i", "\n", $html);
	$html = preg_replace("@<ul>@i", "", $html);
	$html = preg_replace("@</ul>@i", "", $html);

	$html = preg_replace("/margin: /i", "margin:", $html);
	$html = preg_replace("/\s*float: /i", "float:", $html);

	$this->myHTML = $html;
	
	$this->B = $this->I = $this->U = 0;
	$this->HREF = '';
	$this->CENTER = $this->inTable = false;
	
restart:

	$a = preg_split('/<(.*)>/U', $this->myHTML, -1, PREG_SPLIT_DELIM_CAPTURE);
		
if (!is_null($this->fp))
	{
	fwrite ($this->fp, "*** WriteHTML()\n");
	ob_start();
	var_dump ($a);
	$buf = ob_get_clean();
	fwrite ($this->fp, "var_dump (a)\n$buf\n");
	}
	
	$skipPict = $nextItem = -1;		
	foreach($a as $i => $e)
		{
		if (($i < $nextItem) and ($i != $skipPict))
			continue;
			
		if (preg_match ("/^td/i", $e))
			{
			$this->cellData = "";
			for ($j = $i+1; $j < count ($a); $j++)
				{						
				if (preg_match ("@^/td@i", $a[$j]))
					{
					$nextItem = $j;
					break;
					}

	  		if	($j % 2 == 0)
					$this->cellData .= $a[$j];

				else
					{
					if (preg_match ("/^img/i", $a[$j]))
						$skipPict = $j;

					else
						$this->cellData .= "<{$a[$j]}>";
					}
				}
			}

	  if ($i % 2 == 0) // Text
	    {        
		  if (!$this->inTable) # block spurious spaces in html markup
				{
				if ($this->HREF)
				  {
				  $this->SetTextColor(0,0,255);
				  $this->SetStyle('U',true);
				  }
				
	      if ($this->CENTER == true)
					{
					$lines = explode ("\n", $e);
					foreach ($lines as $line)
						{
						if (trim($line)!=='')
							{
							$this->SetX(10);
							$this->Cell ($this->w - 20, 5, $line, 0, 2, 'C', false, $this->HREF);
							}
						}
					}
				else
					$this->Write(5, $e, $this->HREF);
					
				if ($this->HREF)
					{
					$this->SetStyle('U',false);
					$this->SetTextColor(0);
					}		
		    }
	    }
	    			
	  else // Tag
	    {        
      if($e[0] == '/')
          $this->CloseTag(strtoupper(substr($e,1)));
      
      else
      	{
        // Extract attributes
        $a2 = explode(' ',$e);
        $tag = strtoupper(array_shift($a2));
        $attr = array();
        
        foreach($a2 as $v)
          {
          if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
            $attr[strtoupper($a3[1])] = $a3[2];
          }
          
        if ($this->OpenTag(strtoupper($tag), $attr))
        	goto restart;
      	}
	    }
		}
	}
	
  #####################################################
	#                                                   #
	# OpenTag                                           #
	#                                                   #
	#####################################################

	function OpenTag($tag, $attr)
	{
	$retval = false;
	
	switch($tag)
		{
		case 'SUP':
			if( !empty($attr['SUP']) ) 
				{	
				//Set current font to 6pt 	
				$this->SetFont('','',6);
				//Start 125cm plus width of cell to the right of left margin 		
				//Superscript "1" 
				$this->Cell(2,2,$attr['SUP'],0,0,'L');
				}
			break;

		case 'TABLE': // TABLE-BEGIN
			if ( !empty($attr['BORDER']) ) 
				$this->tableborder=$attr['BORDER'];
			else 
				$this->tableborder=0;
  		
  		$this->inTable = true; # disable page breaks
			break;
		
		case 'TR': //TR-BEGIN
			$this->rowY = $this->GetY();
			$this->rowHeight = 0;
			$this->rowWidth  = 0;
			$this->replay = array();
			$this->colnumber = 0;
			$this->trbgcolor = "#ffffff";

			if (!empty($attr['BGCOLOR']) ) 
				$this->trbgcolor=$attr['BGCOLOR'];
			break;
		
		case 'TD': // TD-BEGIN
			if (!empty($attr['WIDTH'])) 
				{
				if (preg_match ("/(\d+)%$/", $attr['WIDTH'], $matches))
					$this->tdwidth=(($matches[1] * $this->rightMargin)/100);
				else
					$this->tdwidth=($attr['WIDTH']/4);
				}
			else 
				$this->tdwidth=40; // Set to your own width if you need bigger fixed cells
			
			if (!empty($attr['HEIGHT']) ) 
				$this->tdheight=($attr['HEIGHT']/6);
			else 
				$this->tdheight=6; // Set to your own height if you need bigger fixed cells
			
			$this->tdalign='L';
			if (!empty($attr['ALIGN']) ) 
				{
				$align=$attr['ALIGN'];		
				if (strtoupper($align) === 'LEFT') 
					$this->tdalign='L';
				
				if (strtoupper($align) === 'CENTER') 
					$this->tdalign='C';
				
				if (strtoupper($align) === 'RIGHT') 
					$this->tdalign='R';
				}
						
			if (!empty($attr['BGCOLOR']) ) 
				$this->tdbgcolor=$attr['BGCOLOR'];
				
			else
				$this->tdbgcolor = $this->trbgcolor;
			
			$this->tdbegin=true;
			$this->replay[$this->colnumber]['x']         = 10 + $this->rowWidth;
			$this->replay[$this->colnumber]['w']         = $this->tdwidth;
			$this->replay[$this->colnumber]['border']    = $this->tableborder;
			$this->replay[$this->colnumber]['alignment'] = $this->tdalign;
			$this->replay[$this->colnumber]['fill']      = $this->tdbgcolor;
			break;

		case 'HR':
			if (!empty($attr['WIDTH']))
				$Width = $attr['WIDTH'];
			else
				$Width = $this->w - $this->lMargin-$this->rMargin;
			
			$x = $this->GetX();
			$y = $this->GetY();
			$this->SetLineWidth(0.2);
			$this->Line($x,$y,$x+$Width,$y);
			$this->SetLineWidth(0.2);
			$this->Ln(1);
			break;
		
		case 'STRONG':
			$this->SetStyle('B',true);
			break;
		
		case 'EM':
			$this->SetStyle('I',true);
			break;
		
		case 'B':
		case 'I':
		case 'U':
			$this->SetStyle($tag,true);
			break;
		
		case 'A':
			$this->HREF=$attr['HREF'];
			break;
		
		case 'IMG':
#			fwrite ($this->fp, "*** IMG {$attr['SRC']}\n");
			if ($this->tdbegin) 
				{
				if (preg_match("/^\./", $attr['SRC']))
					{
					$path =	getcwd();
					$attr['SRC'] = $path. preg_replace ("/^(\.)/", "", $attr['SRC']);
					$attr['SRC'] = realpath ($attr['SRC']);
					$attr['WIDTH'] = preg_replace ("/(px)$/i", "", $attr['WIDTH']);
			#		var_dump ($attr);
					}
				$this->replay[$this->colnumber]['picture'] = $attr['SRC'];
				$this->replay[$this->colnumber]['pw'] = px2mm($attr['WIDTH']/2);
				}
				
			else
		    {
				list($width, $height, $type, $text, $mime, $channels, $bits) = getimagesize($attr['SRC']);
					
				if ($height > $width)
					{
					$pixwidth = 40;
					$pixheight = $height * ($pixwidth / $width);				
					}
				else
					{
					$pixheight = 40;
					$pixwidth = $width * ($pixheight / $height);				
					}

				$this->currentY = $this->getY();
	
				if ($this->currentY + $pixheight + 5 + 3 > $this->bottomMargin)
					{
					$this->addPage($this->orient);
					$this->currentY = 10;
					}
	
				if (preg_match ("/float:right/", $attr['STYLE']))
					{
					$pixheight = ceil ($pixheight / 5) * 5; # round it up to the next line multiple
					$this->Image($attr['SRC'], 10 + $this->rightMargin - $pixwidth, $this->currentY, $pixwidth, 0);
					$this->setXY (10, $this->currentY);
					$this->myHTML = $this->WriteTag($this->rightMargin - $pixwidth - 2, 
					            5, 
					            preg_replace ("@<img\s+style=.*/>@", "", $this->myHTML), 
					            0, # border
					           	'L',
					            false, # fill
					            1, # padding
					            $this->currentY + $pixheight);
					$this->setXY (10, $this->currentY + $pixheight); #+ $pixheight+1);
					$retval = true;
					}
					
				else if (preg_match ("/float:left/", $attr['STYLE']))
					{
					$pixheight = ceil ($pixheight / 5) * 5; # round it up to the next line multiple
					$this->Image($attr['SRC'], 10, $this->currentY, $pixwidth, 0);
					$this->setXY (12 + $pixwidth, $this->currentY);
					$this->myHTML = $this->WriteTag($this->rightMargin - $pixwidth - 2, 
					            5, 
					            preg_replace ("@<img\s+style=.*/>@", "", $this->myHTML), 
					            0, # border
					           	'L',
					            false, # fill
					            1, # padding
					            $this->currentY + $pixheight);
					$this->setXY (10, $this->currentY + $pixheight+1);
					$retval = true;
					}
					
				else
					{
					$this->Image($attr['SRC'], 10 + ($this->rightMargin - $pixwidth)/2, $this->currentY + 3, $pixwidth, 0);					
					$this->currentY += $pixheight + 3;	# some space before the image						
					$this->setY ($this->currentY);
					}											
		  	}
			break;
		
		case 'BLOCKQUOTE':
			$this->Ln(5);
			break;
		
		case 'BR':
	    $this->Ln(5);
	#		$this->firstImage = false;
			break;

		case 'P':
			$this->Ln(10);
			break;
		
		case 'H1':
			$this->Ln(5);
			$this->SetFontSize(22);
			break;
			
		case 'H2':
			$this->Ln(5);
			$this->SetFontSize(18);
			break;

		case 'H3':
			$this->Ln(5);
			$this->SetFontSize(16);
			break;

		case 'H4':
			$this->Ln(5);
			$this->SetFontSize(14);
			break;

		case 'PRE':
			$this->SetFont('Courier','',11);
			$this->SetFontSize(11);
			$this->PRE=true;
			break;

		case 'CENTER':
	#		fwrite ($this->fp, "** CENTER **\n");
			$this->CENTER=true;
			break;

		case 'FONT':
			if (isset($attr['COLOR']) && $attr['COLOR']!='') 
				{
				$coul=hex2dec($attr['COLOR']);
				$this->SetTextColor($coul['R'],$coul['G'],$coul['B']);
				$this->issetcolor=true;
				}
			
			if (isset($attr['FACE']) && in_array(strtolower($attr['FACE']), $this->fontlist)) 
				{
				$this->SetFont(strtolower($attr['FACE']));
				$this->issetfont=true;
				}
			
			if (isset($attr['FACE']) && in_array(strtolower($attr['FACE']), $this->fontlist) && isset($attr['SIZE']) && $attr['SIZE']!='') 
				{
				$this->SetFont(strtolower($attr['FACE']),'',$attr['SIZE']);
				$this->issetfont=true;
				}
			break;
		}
	
	return $retval;
	}

  #####################################################
	#                                                   #
	# CloseTag                                          #
	#                                                   #
	#####################################################

	function CloseTag($tag)
	{
	switch($tag)
		{
		case 'SUP':
			break;

		case 'TABLE':
  		$this->inTable = false; # disable page breaks
			break;

		case 'TR': # replay				
			$this->SetFillColor(255, 255, 255);
			$this->Rect (10, $this->rowY,  $this->rightMargin, $this->rowHeight, "F"); 

      if ($this->CENTER == true)
      	{
				$tableWidth = 0;
				
				for ($i=0; $i<count($this->replay); $i++)
					$tableWidth += $this->replay[$i]['w'];
					
      	$xoffset = ($this->rightMargin - $tableWidth)/2;
      	
				for ($i=0; $i<count($this->replay); $i++)
					$this->replay[$i]['x'] += $xoffset;
      	}

			if ($this->rowY + $this->rowHeight > $this->h - 10) # this is now time to turn the page...
				{
				$this->AddPage($this->pdf->orient);
				$this->rowY = 10;
				$this->setXY (10, 10);
				}		

			for ($i=0; $i<count($this->replay); $i++)
				{
				$coul=hex2dec($this->replay[$i]['fill']);
				$this->SetFillColor($coul['R'],$coul['G'],$coul['B']);

				$this->Rect ($this->replay[$i]['x'], $this->rowY, $this->replay[$i]['w'], $this->rowHeight, "F");

				$this->SetXY ($this->replay[$i]['x'], $this->rowY + ($this->rowHeight - $this->replay[$i]['h'])/2);
				if (!empty ($this->replay[$i]['picture']))
					{
					#	var_dump($this->replay[$i]['picture']);
					list($width, $height, $type, $text) = getimagesize($this->replay[$i]['picture']);
						
					$pixwidth = $this->replay[$i]['pw'];
					$pixheight = $height * ($pixwidth / $width);
								
					$this->Image($this->replay[$i]['picture'], 
												$this->replay[$i]['x'] + ($this->replay[$i]['w'] - $this->replay[$i]['pw'])/2, 
												$this->rowY + ($this->rowHeight - $this->replay[$i]['h'])/2, 
												$this->replay[$i]['pw'], 0);						
					}
				else
					{
					$this->WriteTag($this->replay[$i]['w'], 
					            5, 
					            $this->replay[$i]['text'], 
					            '', 
					           	$this->replay[$i]['alignment'],
					            false);
					}

				if ($this->tableborder)
					$this->Rect ($this->replay[$i]['x'], $this->rowY, $this->replay[$i]['w'], $this->rowHeight, "D");
				}
			$this->SetXY (10, $this->rowY + $this->rowHeight);
			break;
		
		case 'TD': // TD-END
			$coul=hex2dec($this->replay[$this->colnumber]['fill']);
			$this->SetFillColor($coul['R'],$coul['G'],$coul['B']);

			$this->SetXY(10 + $this->rowWidth, $this->rowY);

			$this->replay[$this->colnumber]['text'] = trim($this->cellData); 

			if (!empty ($this->replay[$this->colnumber]['picture']))
				{
#				list($width, $height, $type, $text, $mime, $channels, $bits) = getimagesize($this->replay[$this->colnumber]['picture']);
				list($width, $height, $type, $text) = getimagesize($this->replay[$this->colnumber]['picture']);
					
				$pixwidth = $this->replay[$this->colnumber]['pw'];
				$pixheight = $height * ($pixwidth / $width);
				$this->tdheight = $pixheight;				
						
				$this->replay[$this->colnumber]['h'] = $this->tdheight;
				}
			else	
				{
				$this->WriteTag($this->replay[$this->colnumber]['w'],5, 
										$this->replay[$this->colnumber]['text'], 
				            '',
				           	$this->replay[$this->colnumber]['alignment'],
				           	false);
				
				if ($this->GetY() < $this->rowY) # did we trip a page break?
					$this->rowY = 10;
					
				$this->replay[$this->colnumber]['h'] = $this->GetY() - $this->rowY;
				}
													
			$this->rowWidth += $this->tdwidth;					

			if ($this->replay[$this->colnumber]['h'] > $this->rowHeight)
				$this->rowHeight = $this->replay[$this->colnumber]['h'];
				
			$this->tdbegin=false;
			$this->tdalign="L";
			$this->tdbgcolor="#ffffff";
			$this->colnumber++;
			break;
	
		case 'TABLE': 
			$this->tableborder=0;
			break;
	
		case 'STRONG': 
			$this->SetStyle('B', false);
			break;

		case 'EM':
			$this->SetStyle('I',false);
			break;

		case 'B': 
		case 'I': 
		case 'U': 
			$this->SetStyle($tag, false);
			break;

		case 'A': 
			$this->HREF='';
			break;
	
		case 'H1': 
		case 'H2': 
		case 'H3': 
		case 'H4': 
			$this->Ln(6);
			$this->SetFont('Times','',12);
			$this->SetFontSize(12);
			break;
	
		case 'PRE': 
			$this->SetFont('Times','',12);
			$this->SetFontSize(12);
			$this->PRE=false;
			break;

		case 'CENTER': 
			$this->CENTER=false;
			break;

		case 'FONT': 
			if ($this->issetcolor==true) 
				$this->SetTextColor(0);
			
			if ($this->issetfont) 
				{
				$this->SetFont('arial');
				$this->issetfont=false;
				}
			break;
		}
	}

  #####################################################
	#                                                   #
	# PutLink                                           #
	#                                                   #
	#####################################################

	function PutLink($URL, $txt)
	{
	// Put a hyperlink
	$this->SetTextColor(0,0,255);
	$this->SetStyle('U',true);
	$this->Write(5,$txt,$URL);
	$this->SetStyle('U',false);
	$this->SetTextColor(0);
	}

  #####################################################
	#                                                   #
	# Footer                                            #
	#                                                   #
	#####################################################

	function Footer()
	{
  if($this->_numberingFooter == false)
    return;

	// Position at 1.5 cm from bottom
	$this->SetY(-15);
	// Arial italic 8
	$this->SetFont('Arial','I',8);
	// Page number
	$pageNo = $this->PageNo();
	$pageLabel = sprintf ("Page %d", $pageNo);
	$pageNo++;
	$this->SetTextColor(0);
	$this->Cell(0,10,$pageLabel,0,0,'C');
	}
	
  #####################################################
	#                                                   #
	# AddPage                                           #
	#                                                   #
	#####################################################

  function AddPage($orientation='', $format='') 
  {
  parent::AddPage($orientation,$format);
  
	if($this->_numbering == true)
		$this->_numPageNum++;
  }

  #####################################################
	#                                                   #
	# startPageNums                                     #
	#                                                   #
	#####################################################

  function startPageNums() 
  {
  $this->_numbering=true;
  $this->_numberingFooter=true;
  }

  #####################################################
	#                                                   #
	# stopPageNums                                      #
	#                                                   #
	#####################################################

  function stopPageNums() 
  {
  $this->_numbering=false;
  $this->_numberingFooter=false;
  }

  #####################################################
	#                                                   #
	# numPageNo                                         #
	#                                                   #
	#####################################################

  function numPageNo() 
  {
  return $this->_numPageNum;
  }

  #####################################################
	#                                                   #
	# Bookmark                                          #
	#                                                   #
	#####################################################

	function Bookmark($txt, $level=0, $y=0)
	{
	if($y==-1)
		$y=$this->GetY();
	$this->outlines[]=array('t'=>$txt, 'l'=>$level, 'y'=>($this->h-$y)*$this->k, 'p'=>$this->PageNo());
	}

  #####################################################
	#                                                   #
	# BookmarkUTF8                                      #
	#                                                   #
	#####################################################
	
	function BookmarkUTF8($txt, $level=0, $y=0)
	{
	$this->Bookmark($this->_UTF8toUTF16($txt),$level,$y);
	}
	
  #####################################################
	#                                                   #
	# _putbookmarks                                     #
	#                                                   #
	#####################################################

	function _putbookmarks()
	{
	$nb=count($this->outlines);
	if($nb==0)
		return;
	$lru=array();
	$level=0;
	foreach($this->outlines as $i=>$o)
		{
		if($o['l']>0)
			{
			$parent=$lru[$o['l']-1];
			//Set parent and last pointers
			$this->outlines[$i]['parent']=$parent;
			$this->outlines[$parent]['last']=$i;
			if($o['l']>$level)
				{
				//Level increasing: set first pointer
				$this->outlines[$parent]['first']=$i;
				}
			}
		else
			$this->outlines[$i]['parent']=$nb;
		if($o['l']<=$level and $i>0)
			{
			//Set prev and next pointers
			$prev=$lru[$o['l']];
			$this->outlines[$prev]['next']=$i;
			$this->outlines[$i]['prev']=$prev;
			}
		$lru[$o['l']]=$i;
		$level=$o['l'];
		}
	//Outline items
	$n=$this->n+1;
	foreach($this->outlines as $i=>$o)
		{
		$this->_newobj();
		$this->_out('<</Title '.$this->_textstring($o['t']));
		$this->_out('/Parent '.($n+$o['parent']).' 0 R');
		if(isset($o['prev']))
			$this->_out('/Prev '.($n+$o['prev']).' 0 R');
		if(isset($o['next']))
			$this->_out('/Next '.($n+$o['next']).' 0 R');
		if(isset($o['first']))
			$this->_out('/First '.($n+$o['first']).' 0 R');
		if(isset($o['last']))
			$this->_out('/Last '.($n+$o['last']).' 0 R');
		$this->_out(sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]',1+2*$o['p'],$o['y']));
		$this->_out('/Count 0>>');
		$this->_out('endobj');
		}
	//Outline root
	$this->_newobj();
	$this->OutlineRoot=$this->n;
	$this->_out('<</Type /Outlines /First '.$n.' 0 R');
	$this->_out('/Last '.($n+$lru[0]).' 0 R>>');
	$this->_out('endobj');
	}
	
  #####################################################
	#                                                   #
	# _putresources                                     #
	#                                                   #
	#####################################################

	function _putresources()
	{
  parent::_putresources();
  $this->_putbookmarks();
	}
	
  #####################################################
	#                                                   #
	# _putcatalog                                       #
	#                                                   #
	#####################################################

	function _putcatalog()
	{
	parent::_putcatalog();
	if(count($this->outlines)>0)
		{
			$this->_out('/Outlines '.$this->OutlineRoot.' 0 R');
	#		$this->_out('/PageMode /UseOutlines');
		}
	}

} # end of class

###########################################################################
#                                                                         #
# hex2dec                                                                 #
#                                                                         #
###########################################################################

function hex2dec($couleur = "#000000")
{
$R = substr($couleur, 1, 2);
$rouge = hexdec($R);
$V = substr($couleur, 3, 2);
$vert = hexdec($V);
$B = substr($couleur, 5, 2);
$bleu = hexdec($B);
$tbl_couleur = array();
$tbl_couleur['R']=$rouge;
$tbl_couleur['G']=$vert;
$tbl_couleur['B']=$bleu;
return $tbl_couleur;
}

###########################################################################
#                                                                         #
# px2mm                                                                   #
#                                                                         #
###########################################################################
//conversion pixel -> millimeter in 72 dpi
function px2mm($px)
{
return $px*25.4/72;
}

    #####################################################
		#                                                   #
		# GenerateEbook                                     #
		#                                                   #
		#####################################################

		function GenerateEbook ($myfile) 
		{
		$myPDF=new PDF2();
		
		# metadata
		$myPDF->title = "html2pdf test";
		$myPDF->subject = "eBook";
		$myPDF->author = "somebody";
		$myPDF->keywords = "keyword";

		$myPDF->orient = 'P';
				
		$myPDF->bottomMargin = 297 - 10; # A4 in mm - 2 margins
		$myPDF->rightMargin  = 210 - 20; # letter in mm - 2 margins
				
		$myPDF->AddPage($myPDF->orient);
		$myPDF->currentY = 10;
/*		$myPDF->Bookmark("Cover");

		$myPDF->SetFillColor (150);
		$myPDF->SetTextColor (255);
 		$myPDF->SetFont('Arial','',24);
		$myPDF->MultiCell(0, 24, $GLOBALS['title'], 1, 'C', true);

		$myPDF->SetFillColor (255); # causes page number to show on front page ?
		$myPDF->SetTextColor (0);
		
		$myPDF->currentY = $myPDF->getY() + 10;

		$src = "";
		$mySpecialBlogs = array 
		(
		"handyfolks.com.au"  => "http://handyfolks.com.au/img/pier.jpg", 
		"trendauthority.net" => "http://trendauthority.net/img/nightbridge.jpg",
		"social-buzz.info"      => "http://social-buzz.info/img/snowpeak.jpg",
		);
		
		if (array_key_exists ($_SERVER['SERVER_NAME'], $mySpecialBlogs))
			$src = $mySpecialBlogs[$_SERVER['SERVER_NAME']];
		
		else	
			{
			$url = preg_replace ("/index\.php/", "", "http://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME']);			
			$url .= "blogheader.html";
			$page = file_get_contents ($url);
			
			if (preg_match ("@<img\s+class=\"bannerimg\"\s+src=\"(\S+)\".+/>@i", $page, $matches))
				$src = $matches[1];
			}
		
		if ($src !== "")
			{
			list($width, $height, $type, $text, $mime, $channels, $bits) = getimagesize($src);
					
			$pixwidth = 190;
			$pixheight = $height * ($pixwidth / $width);
				
			
			$myPDF->Image($src, 10 + (($myPDF->rightMargin - $pixwidth)/2), $myPDF->currentY, $pixwidth, 0);
			$myPDF->currentY += $pixheight + 10;
			}
			
		$myPDF->SetXY(10 + ($myPDF->rightMargin - 100)/2, $myPDF->currentY);
		
		$myPDF->SetFont('Times','I',8);
		$myPDF->MultiCell(100, 5, "Compilation of blog articles\n(C) ".date('Y')." Bruno Deshayes", 0, 'C', false);

		$myPDF->SetFont('Times','',10);
		$myPDF->SetTextColor (0, 0, 255);
#		$url = preg_replace ("/index\.php/", "", "http://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME']);			
		$url = $this->myBlogs[$GLOBALS['title']];
		$myPDF->Cell(0, 5, $url, 0, 0, "C", false, $url);
		$myPDF->SetTextColor (0);
		
#		if (!($db = sqlite_open(SYSTEM))) 
#	    die('SQL ERROR: Open failed: ' . sqlite_error_string(sqlite_last_error($db)));
						
		$today = date('Ymd');
		$sql = "select * from blogentries where entry = 0 and accepted = 1 and log_date <= $today order by log_date desc;";

		if (!($result = sqlite_query($this->sysdb, $sql))) 
	    die('SQL ERROR: ' . sqlite_error_string(sqlite_last_error($this->sysdb)) . " - Query was: {$sql}");
					
    # do TOC
                            
    $myPDF->AddPage($this->orient);
		$myPDF->startPageNums();
		$myPDF->Bookmark("Contents");

    $myPDF->SetFont('Times','B',14);
    $myPDF->Cell(0,5,'Table of Contents',0,1,'C');
    $myPDF->Ln(10);

		$myArticles = array();
		
		while ($columns = sqlite_fetch_array($result)) 
			{
			fixSQLquote ($columns);

			if ((!preg_match ("/support/", $columns['tags'])))
				{
				$str = $columns['title'];
				$myArticles[$columns['id']] = $myPDF->AddLink();
				$myPDF->SetFont('Times','B',10);
				
				$myPDF->SetTextColor(0,0,255);
				$myPDF->SetStyle('U',true);
				$myPDF->Cell($strsize+2,$this->FontSize+2,$str, 0, 0, 'L', false, $myArticles[$columns['id']]);
				$myPDF->SetStyle('U',false);
				$myPDF->SetTextColor(0);
				$myPDF->Ln(5);
				}
			}

		$resId = 0;
		$resStr = "Resources";
		$myArticles[$resId] = $myPDF->AddLink();
		$myPDF->SetFont('Times','B',10);
		
		$myPDF->SetTextColor(0,0,255);
		$myPDF->SetStyle('U',true);
		$myPDF->Cell($strsize+2,$this->FontSize+2,$resStr, 0, 0, 'L', false, $myArticles[$resId]);
		$myPDF->SetStyle('U',false);
		$myPDF->SetTextColor(0);
		$myPDF->Ln(5);

		if (!($result = sqlite_query($this->sysdb, $sql))) 
	    die('SQL ERROR: ' . sqlite_error_string(sqlite_last_error($this->sysdb)) . " - Query was: {$sql}");
		
		while ($columns = sqlite_fetch_array($result)) 
			{
			fixSQLquote ($columns);
			
			if ((!preg_match ("/support/", $columns['tags'])))
				{
				$myPDF->AddPage($myPDF->orient);
									
				$myPDF->currentY = 10;
	
				$myPDF->SetLink($myArticles[$columns['id']], -1, -1);
				$myPDF->Bookmark($columns['title']);

				$myPDF->SetFillColor (150);
				$myPDF->SetTextColor (255);
	   		$myPDF->SetFont('Arial','',14);
				$myPDF->MultiCell(0, 16, $columns['title'], 1, 'C', true);
				$myPDF->Ln(5);
				$myPDF->currentY = $myPDF->getY();
	
				$myPDF->SetFillColor (255);
				$myPDF->SetTextColor (0);
	   		$myPDF->SetFont('Times','',10);
	   		
				if (preg_match ("/subscription/", $columns['tags']) and ($_SESSION['paid'] != 1))
					{
					$stream  = trim(preg_replace ("/\n.+/", "", $columns['memo']));
					$stream .= "...<br /><i>To read the full article <a href=\"http://{$_SERVER['HTTP_HOST']}?account=new\">take a subscription</a></i>";
					$stream .= "<br /><br />";
					$myPDF->WriteHTML($stream);
					}
					
				else
					$myPDF->WriteHTML($columns['memo']);
			  }
		  }			
				
		$myPDF->AddPage($pdf->orient);
							
		$myPDF->SetLink($myArticles[$resId], -1, -1);
		$myPDF->Bookmark($resStr);
*/
		$myPDF->SetFillColor (255);
		$myPDF->SetTextColor (0);
	  $myPDF->SetFont('Times','',10);
 		
		$myPDF->WriteHTML(file_get_contents($myfile));
		$myOutput = preg_replace("/\.html$/i", ".pdf", $myfile);
		$myPDF->Output($myOutput, 'F');
		return $myOutput;
		}
		
$output = GenerateEbook ($_SERVER['argv'][1]);
echo "output to $output\n";
exit;
?>