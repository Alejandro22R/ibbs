# Tailwind para portal_alumno.php — compilado localmente

`ibbs-alumno.css` es el único archivo que usa `portal_alumno.php`. Antes la
página cargaba Tailwind en vivo desde `cdn.tailwindcss.com`; si esa conexión
fallaba o era lenta (red del instituto, usuarios con internet débil), toda
la página perdía sus clases y se veía como una columna de texto gigante sin
ningún estilo. Ahora es un CSS estático, igual que las fuentes y las demás
librerías en `assets/libs/`: no depende de internet para verse bien.

## Cómo regenerarlo

Hace falta solo si se agregan o cambian clases de Tailwind dentro de
`portal_alumno.php` (es el único archivo escaneado).

```bash
cd assets/libs/tailwind/build
npm install -D tailwindcss@3        # una sola vez
npx tailwindcss -i input.css -o ../ibbs-alumno.css --minify
```

`tailwind.config.js` tiene el mismo tema (`ibbs.*` colores + fuentes) que
antes vivía en el `<script>tailwind.config = {...}</script>` del archivo.
Si ese tema cambia, actualizar acá también.
