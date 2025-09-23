<?php 

require_once(PLUGIN_TERME_PATH . 'fpdf/fpdf.php');

if (! defined('LOGO_TERME_PATH')) {
	define( 'LOGO_TERME_PATH', PLUGIN_TERME_PATH.'assets/header-la-via-delle-terme.jpg' );
}

class PDF extends FPDF
{
// Page header  
function Header()
{
    // global $title;
    // Logo
    $this->Image(LOGO_TERME_PATH,0,0,210,0);
    $this->Ln(40);
    // // Arial bold 15
    // $this->SetFont('Arial','B',24);
    // $this->SetTextColor(0, 116, 160);
    // // Move to the right
    // $this->Cell(0,10,$title,0,1,'C');
    // $this->Ln(10);
    // $this->Cell(0, 10, $this->title, 1, 1, 'C');
    // // Title
    // // $this->Cell(30,10,"$title",1,0,'C');
    // // Line break
}

// Page footer
function Footer()
{   
    // $this->Ln(20);
    // $this->Image(LOGO_TERME_FOOTER_PATH, 12, $pdf->GetY(),198, 0);
    // Position at 1.5 cm from bottom
    //  $this->Ln(10);
    // $this->SetY(0);
    // $this->Image(LOGO_TERME_FOOTER_PATH,0,0,210,0);
    //  $this->Ln(40);
    // Arial italic 8
    // $this->SetFont('Arial','I',8);
    // // Page number
    // $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
}
}

