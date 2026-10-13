<?php
/**
 * IBBS — "Acerca de" / información del instituto.
 *
 * Partial compartido por portal_alumno.php y portal_docente.php (dos
 * páginas con sistemas de CSS distintos — una usa utilidades Tailwind
 * propias, la otra sus propias clases .sb-*) así que este bloque NO
 * depende de ninguna de las dos: usa solo las variables de color ya
 * compartidas por ambas vía assets/ibbs.css (--ink, --lime, --paper,
 * --cream, --border, --muted), más un puñado de clases .acerca-*
 * propias, para que se vea igual y correcto en cualquiera de los dos
 * portales, en claro y en oscuro.
 *
 * Contenido (textos) tal cual lo mandó la institución — no se corrigió
 * tipografía ni redacción, solo se le dio el mismo diseño que el resto
 * de la aplicación. Se muestra automáticamente cada vez que el alumno
 * o el docente entra al portal (ver el <script> al final) y se puede
 * reabrir en cualquier momento desde "Acerca de" en el menú lateral.
 */
?>
<style>
.acerca-overlay{position:fixed;inset:0;z-index:9999;background:rgba(26,77,46,.78);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;padding:1.2rem;}
.acerca-overlay.acerca-open{display:flex;}
.acerca-modal{background:var(--paper);color:var(--ink);width:100%;max-width:880px;max-height:92vh;border-radius:18px;overflow:hidden;box-shadow:0 30px 70px rgba(0,0,0,.35);position:relative;border-top:4px solid var(--lime2);font-family:'Nunito',sans-serif;}
.acerca-close{position:absolute;top:14px;right:14px;z-index:5;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.18);color:#fff;border:none;font-size:1.5rem;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.acerca-close:hover{background:rgba(255,255,255,.32);}
.acerca-scroll{max-height:92vh;overflow-y:auto;}
.acerca-hero{background:linear-gradient(135deg,#1a4d2e 0%,#1e5c36 100%);color:#fff;padding:2.6rem 2rem 2.2rem;text-align:center;position:relative;overflow:hidden;}
.acerca-hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(57,255,20,.08) 1px,transparent 1px);background-size:20px 20px;pointer-events:none;}
.acerca-logo{width:72px;height:72px;border-radius:16px;object-fit:cover;margin:0 auto 1rem;box-shadow:0 0 20px rgba(57,255,20,.35);position:relative;}
.acerca-hero h1{font-family:'Playfair Display',serif;font-weight:700;font-size:1.55rem;margin:0 0 .9rem;position:relative;}
.acerca-verse{font-style:italic;color:rgba(255,255,255,.78);font-size:.84rem;max-width:580px;margin:0 auto;line-height:1.65;position:relative;}
.acerca-section{padding:2rem;}
.acerca-section-alt{background:var(--cream);}
.acerca-section h2{font-family:'Playfair Display',serif;font-weight:700;color:var(--ink);font-size:1.25rem;margin:0 0 .5rem;text-align:center;}
.acerca-verse-alt{text-align:center;font-style:italic;color:var(--muted);font-size:.82rem;max-width:580px;margin:0 auto 1.5rem;line-height:1.65;}
.acerca-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.1rem;}
.acerca-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:.9rem;}
@media(max-width:640px){.acerca-grid-2,.acerca-grid-3{grid-template-columns:1fr;}.acerca-section{padding:1.4rem;}.acerca-hero{padding:2rem 1.4rem 1.8rem;}}
.acerca-card{background:var(--paper);border:1px solid var(--border);border-radius:14px;padding:1.3rem;}
.acerca-card h3{font-family:'Playfair Display',serif;font-weight:700;color:var(--ink);font-size:1.02rem;margin:0 0 .6rem;}
.acerca-card p{color:var(--muted);font-size:.84rem;line-height:1.75;margin:0;}
.acerca-contact{display:flex;flex-direction:column;align-items:center;gap:.5rem;background:var(--paper);border:1px solid var(--border);border-radius:14px;padding:1.2rem .8rem;text-decoration:none;color:var(--ink);font-weight:700;font-size:.84rem;transition:transform .15s,box-shadow .15s;}
.acerca-contact:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,.1);}
.acerca-contact i{font-size:1.5rem;color:var(--lime2);}
.acerca-highlight{background:linear-gradient(135deg,#1a4d2e 0%,#1e5c36 100%);color:#fff;border-radius:14px;padding:1.6rem;text-align:center;}
.acerca-highlight i{font-size:1.7rem;color:var(--lime);margin-bottom:.6rem;display:block;}
.acerca-highlight h2{color:#fff;font-size:1.08rem;margin:0 0 .4rem;}
.acerca-highlight p{color:rgba(255,255,255,.78);font-size:.84rem;margin:0;}
.acerca-btn{display:block;width:fit-content;margin:1.6rem auto 0;background:var(--ink);color:var(--lime);padding:.7rem 1.9rem;border-radius:30px;text-decoration:none;font-weight:700;font-size:.84rem;transition:opacity .15s;}
.acerca-btn:hover{opacity:.85;}
.acerca-footer{padding:1.3rem 2rem;text-align:center;border-top:1px solid var(--border);}
.acerca-footer p{color:var(--muted);font-size:.7rem;margin:0;}
</style>

<div class="acerca-overlay" id="acercaOverlay" onclick="if(event.target===this) cerrarAcercaDe();">
  <div class="acerca-modal">
    <button type="button" class="acerca-close" onclick="cerrarAcercaDe()" aria-label="Cerrar">&times;</button>
    <div class="acerca-scroll">

      <div class="acerca-hero">
        <img src="assets/logo.jpg" class="acerca-logo" alt="Logo del instituto">
        <h1>Instituto Biblico Bautista del Sur</h1>
        <p class="acerca-verse">2 de Timoteo 2:15 "Procura con diligencia presentarte ante Dios aprobado, como obrero que no tiene de qué avergonzarse, que expone bien la palabra de verdad"</p>
      </div>

      <section class="acerca-section">
        <h2>Metodos de Estudio</h2>
        <p class="acerca-verse-alt">Hechos 1:8 "Pero recibirán poder cuando el Espíritu Santo haya venido sobre ustedes, y me serán testigos en Jerusalén, en toda Judea, en Samaria y hasta lo último de la tierra."</p>
        <div class="acerca-grid-2">
          <div class="acerca-card">
            <h3>Programa</h3>
            <p>El instituto biblico bautista  del sur cuenta con un programa de  estudio  que  consta de 5 años para ser licenciado en teologia obteniendo asi es tan prestigioso titulo consta  de 5 secciones de estudiantes desde primer año hasta 5to año arduamente enseñando la palabra divina con los profesores profesionales mallisters doctores, en si muchos hermanos  e incluso pastores se preparan dia  a dia  para hacerte crecer en el conocimiento de Dios y su palabra siendo asi el instituto biblico bautista mas grande del estadi  bolivar.</p>
          </div>
          <div class="acerca-card">
            <h3>Programa a distancia</h3>
            <p>Para esos siervos y siervas del señor que no tiene la oportunidad  de estar en una de nuestras cedes  , tiene  la opcion de acompañarnos via  online a traves de nuestro diplomado donde si bien  es a distancia posees  la oportunidad de ver las calses con los mismos profesores de noche  y todas las pruebas las ves  virtual, no  hay  excusa  para estudiar  del señor todo esta a tu  disposicion  para convertirte en un  siervo aprobado con  muchas  ganas de seguir el ejemplo de jesus.</p>
          </div>
        </div>
      </section>

      <section class="acerca-section acerca-section-alt">
        <h2>Contactos</h2>
        <div class="acerca-grid-3">
          <a class="acerca-contact" href="https://www.instagram.com/institutobiblicobautistadelsur?igsh=ZGthNTl5Nzhpd2Vu" target="_blank" rel="noopener">
            <i class="fa-brands fa-instagram"></i><span>Instagram</span>
          </a>
          <a class="acerca-contact" href="https://www.facebook.com/share/19TjuHRvMY/" target="_blank" rel="noopener">
            <i class="fa-brands fa-facebook"></i><span>Facebook</span>
          </a>
          <a class="acerca-contact" href="mailto:institutobiblicoautistadelsur@gmail.com">
            <i class="fa-solid fa-envelope"></i><span>Gmail</span>
          </a>
        </div>
      </section>

      <section class="acerca-section">
        <div class="acerca-grid-2">
          <div class="acerca-highlight">
            <i class="fa-solid fa-campground"></i>
            <h2>Actividades Recreativas</h2>
            <p>Campamento Teologico 2024</p>
          </div>
          <div class="acerca-highlight">
            <i class="fa-solid fa-graduation-cap"></i>
            <h2>Actos de Grado</h2>
            <p>Desde 1997 hasta 2025. 28 años regalando alegria a los corazones.</p>
          </div>
        </div>
      </section>

      <section class="acerca-section acerca-section-alt">
        <h2>INSCRIPCIONES</h2>
        <div class="acerca-grid-2">
          <div class="acerca-card">
            <h3>Presenciales</h3>
            <p>Aperturadas todos los años desde febrero hasta marzo para iniciar nuestro instituto biblico bautistad el sur presencial desde las 8am hasta las 2pm con un costo de inscripcion y mesualidad que no  encontraras en otro lugar si n embargo si deseas ver una materia en especifico ponte en contacto con nosotos para ingresar en una  de nuestras modalidades bien sea esta o el diplomado</p>
          </div>
          <div class="acerca-card">
            <h3>Sedes</h3>
            <p>Estamos ubicados en Puerto Ordaz, San Félix, upata ,el callao ,angostura del Orinoco o ciudad Bolívar ,Barcelona y sierra caroni . Cada una de estas haciendo una sede con la misma calidad de profesores solamente que con diferentes caras dispuesto a brindarte todo el apoyo y recursos necesarios para entender la palabra divina</p>
          </div>
        </div>
        <a href="#" class="acerca-btn" onclick="return false;">Informacion</a>
      </section>

      <div class="acerca-footer">
        <p>Experiencia Demostrada de Calidad desde 1997 - 2025 Instituto Bíblico Autista del Sur. Todos los derechos reservados</p>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  function abrirAcercaDe(){
    var ov = document.getElementById('acercaOverlay');
    if (ov) ov.classList.add('acerca-open');
  }
  function cerrarAcercaDe(){
    var ov = document.getElementById('acercaOverlay');
    if (ov) ov.classList.remove('acerca-open');
  }
  window.abrirAcercaDe = abrirAcercaDe;
  window.cerrarAcercaDe = cerrarAcercaDe;
  // Se muestra sola cada vez que el alumno/docente entra al portal —
  // la puede cerrar con la X, y reabrirla cuando quiera desde "Acerca
  // de" en el menú lateral.
  document.addEventListener('DOMContentLoaded', abrirAcercaDe);
})();
</script>
