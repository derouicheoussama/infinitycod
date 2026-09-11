param(
    [string]$Src,
    [int]$X,
    [int]$Y,
    [int]$W,
    [int]$H,
    [int]$Scale,
    [string]$Out
)
Add-Type -AssemblyName System.Drawing
$image = [System.Drawing.Image]::FromFile($Src)
$bmp = New-Object System.Drawing.Bitmap ($W * $Scale), ($H * $Scale)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::NearestNeighbor
$destRect = New-Object System.Drawing.Rectangle 0, 0, ($W * $Scale), ($H * $Scale)
$srcRect = New-Object System.Drawing.Rectangle $X, $Y, $W, $H
$g.DrawImage($image, $destRect, $srcRect, [System.Drawing.GraphicsUnit]::Pixel)
$bmp.Save($Out)
$g.Dispose()
$bmp.Dispose()
$image.Dispose()
Write-Output "OK $Out"
