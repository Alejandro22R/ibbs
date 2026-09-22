-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: asegurar que 'alumno' exista como rol válido
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 008_asegurar_rol_alumno.sql`).
-- Es seguro volver a correrlo.
--
-- Si tu base de datos se creó a partir de una versión vieja de
-- database/ibbs.sql, la columna `usuarios.rol` puede ser un ENUM que
-- todavía no incluya 'alumno' — en ese caso, cualquier INSERT con
-- rol='alumno' fallaría (o, en modo no estricto, guardaría un valor
-- vacío en vez de 'alumno'), y el registro público de alumnos nunca
-- funcionaría bien sin importar qué diga el código PHP.
--
-- Este ALTER redefine el ENUM completo (con las 4 opciones que ya usa
-- el resto del sistema) y deja 'alumno' como valor por defecto.
-- Redefinirlo con las mismas opciones no rompe las cuentas existentes.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `usuarios`
  MODIFY `rol` ENUM('superadmin','admin','profesor','alumno') DEFAULT 'alumno';
