<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;

class BarcodeController extends Controller
{
    /**
     * Generate a 1D Barcode (e.g., Code 128)
     */
    public function generate1D(Request $request)
    {
        $request->validate([
            'value' => 'required|string',
        ]);

        $d = new DNS1D();
        
        // Generate raw SVG inline string
        // Parameters: getBarcodeSVG($code, $type, $widthFactor, $height)
        $svgRaw = $d->getBarcodeSVG($request->value, 'C128', 3, 80);

        return response()->json([
            'status' => 'success',
            'type' => 'CODE128',
            'value' => $request->value,
            'svg_raw' => $svgRaw,
            // Alternative: Return as HTML image source tag compatible base64
            'base64' => 'data:image/png;base64,' . $d->getBarcodePNGPath($request->value, 'C128'),
        ]);
    }

    /**
     * Generate a 2D Barcode (e.g., QR Code)
     */
    public function generate2D(Request $request)
    {
        $request->validate([
            'value' => 'required|string',
        ]);

        $d = new DNS2D();
        
        // Parameters: getBarcodeSVG($code, $type, $width, $height, $color)
        $svgRaw = $d->getBarcodeSVG($request->value, 'QRCODE', 5, 5);

        return response()->json([
            'status' => 'success',
            'type' => 'QRCODE',
            'value' => $request->value,
            'svg_raw' => $svgRaw,
        ]);
    }
}
