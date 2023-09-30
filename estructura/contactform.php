
<!-- Contact -->
<div id="contact" class="form-1">
    <?php

    //Import PHPMailer classes into the global namespace
        //These must be at the top of your script, not inside a function
        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\SMTP;
        use PHPMailer\PHPMailer\Exception;

        //Load Composer's autoloader
        require 'configuracion/PHPMailer/src/Exception.php'; 
        require 'configuracion/PHPMailer/src/PHPMailer.php';
        require 'configuracion/PHPMailer/src/SMTP.php';




    if (isset($_POST['submit'])) {

        $nombre = $_POST["nombre"];
        $email = $_POST["email"];
        $mensaje = $_POST["mensaje"];

        

        //Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);
        // var_dump($mail);
        try {
            //Server settings
            $mail->SMTPDebug = 0;                      //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = 'mail.servicombasculas.com.mx';                     //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $mail->Username   = 'contacto@servicombasculas.com.mx';                     //SMTP username
            $mail->Password   = '744920lovepass';                               //SMTP password
            $mail->SMTPSecure = 'ssl';            //Enable implicit TLS encryption
            $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom($email, $nombre);
            $mail->addAddress('contacto@servicombasculas.com.mx', 'servicombasculas.com.mx');     //Add a recipient
            //$mail->addAddress('admonbasculasdigitales@gmail.com', 'servicombasculas.com.mx'); 
            //$mail->addAddress('isarel.navarrete229@gmail.com', 'servicombasculas.com.mx');     //Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');
            //$mail->addCC('cc@example.com');
            //$mail->addBCC('bcc@example.com');

            //Attachments
            //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = 'Email desde formulario servicombasculas.com.mx';
            $mail->Body    = ($mensaje);
            $mail ->CharSet = 'utf-8';
            //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            $mailstats = 'Gracias por comunicarte! En breve nos pondremos en contacto contigo';
        } catch (Exception $e) {
            $mailstats = "Error, Mensaje no enviado. Mail Error: {$mail->ErrorInfo}";
        }
    }    
    ?>

    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <h2 class="h2-heading">Contacto</h2>
                <p class="p-heading">Solicta tu cotizacion, Gracias por comunicarte en breve nos pondremos en contacto</p>
                <ul class="list-unstyled li-space-lg">
                    <li><i class="fas fa-map-marker-alt"></i> &nbsp;Servicom, Querétaro, Qro 76116, MX</li>
                    <li><i class="fas fa-phone"></i> &nbsp;<a href="tel:4423601166">442 360 11 66</a></li>
                    <li><i class="fas fa-phone"></i> &nbsp;<a href="tel:4421782616">442 178 26 16</a></li>
                    <li><i class="fas fa-envelope"></i> &nbsp;<a href="mailto:contacto@servicombasculas.com.mx">contacto@servicombasculas.com.mx</a></li>
                </ul>
            </div> <!-- end of col -->
        </div> <!-- end of row -->

        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                
                <!-- Contact Form -->
                <form method="POST" action="#contact">
                    <div class="form-group">
                        <input type="text" class="form-control-input" placeholder="Nombre" required name="nombre">
                    </div>
                    <div class="form-group">
                        <input type="email" class="form-control-input" placeholder="Email" required name="email">
                    </div>
                    <div class="form-group">
                        <textarea class="form-control-textarea" placeholder="Mensaje" required name="mensaje"></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="submit" class="form-control-submit-button">Enviar</button>
                    </div>
                </form>
                <!-- end of contact form -->

            </div> <!-- end of col -->
        </div> <!-- end of row -->


        <?php if (isset($mailstats)) { ?>
            <div class="row py-3">
                <div class="col-lg-6 col-md-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $mailstats; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        <?php } ?><!-- end of row -->

    </div> <!-- end of container -->

</div> <!-- end of form-1 -->
<!-- end of contact -->