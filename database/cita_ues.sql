-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-11-2026 a las 03:46:04
-- Versión del servidor: 10.11.16-MariaDB
-- Versión de PHP: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `cita_ues`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id_asistencia` int(11) NOT NULL,
  `id_cita` int(11) NOT NULL,
  `id_profesional` int(11) NOT NULL,
  `estado_asistencia` enum('ASISTIO','NO_ASISTIO') NOT NULL,
  `observacion` text DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asistencias`
--

INSERT INTO `asistencias` (`id_asistencia`, `id_cita`, `id_profesional`, `estado_asistencia`, `observacion`, `fecha_registro`) VALUES
(1, 1, 2, 'ASISTIO', 'El estudiante asistió a su asesoría académica', '2026-11-14 14:25:14'),
(2, 3, 2, 'ASISTIO', '', '2026-11-15 14:26:22'),
(5, 4, 2, 'NO_ASISTIO', '', '2026-11-15 15:09:36'),
(6, 5, 2, 'ASISTIO', '', '2026-11-15 18:06:09'),
(7, 7, 2, 'ASISTIO', '', '2026-11-15 18:15:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_disponibilidad` int(11) NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('PENDIENTE','CONFIRMADA','CANCELADA','FINALIZADA') NOT NULL DEFAULT 'PENDIENTE',
  `motivo` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `citas`
--

INSERT INTO `citas` (`id_cita`, `id_estudiante`, `id_disponibilidad`, `fecha_solicitud`, `estado`, `motivo`, `observaciones`, `hora_inicio`, `hora_fin`) VALUES
(1, 1, 2, '2026-11-14 14:19:53', 'FINALIZADA', 'Asesoría sobre materias universitarias', NULL, '08:30:00', '09:00:00'),
(2, 1, 2, '2026-11-15 10:41:46', 'CANCELADA', 'Consulta sobre datos de prueba', NULL, '08:00:00', '08:30:00'),
(3, 1, 2, '2026-11-15 14:25:20', 'FINALIZADA', 'Consulta', NULL, '09:00:00', '09:30:00'),
(4, 1, 2, '2026-11-15 15:08:44', 'FINALIZADA', 'Asesoria', NULL, '10:00:00', '10:30:00'),
(5, 1, 2, '2026-11-15 16:08:56', 'FINALIZADA', 'Cita', NULL, '11:30:00', '12:00:00'),
(6, 1, 2, '2026-11-15 18:04:04', 'PENDIENTE', 'Cita', NULL, '09:30:00', '10:00:00'),
(7, 1, 2, '2026-11-15 18:15:25', 'FINALIZADA', 'Cita 2', NULL, '11:00:00', '11:30:00'),
(8, 1, 6, '2026-11-15 19:06:21', 'CONFIRMADA', 'hola', NULL, '17:00:00', '17:20:00'),
(9, 1, 6, '2026-11-15 19:06:41', 'PENDIENTE', 'nose', NULL, '16:00:00', '16:20:00'),
(10, 1, 6, '2026-11-15 19:06:56', 'PENDIENTE', 'hdej', NULL, '16:00:00', '16:20:00'),
(11, 1, 6, '2026-11-15 19:07:08', 'CONFIRMADA', 'rgstrndhy', NULL, '17:40:00', '18:00:00'),
(12, 1, 6, '2026-11-15 19:07:24', 'PENDIENTE', 'tgyhtuy', NULL, '16:00:00', '16:20:00'),
(13, 1, 6, '2026-11-15 19:07:43', 'CANCELADA', 'jiumuy', NULL, '16:20:00', '16:40:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidades`
--

CREATE TABLE `disponibilidades` (
  `id_disponibilidad` int(11) NOT NULL,
  `id_profesional_servicio` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `cupo_maximo` int(11) NOT NULL DEFAULT 1,
  `estado` enum('DISPONIBLE','BLOQUEADO','FINALIZADO') NOT NULL DEFAULT 'DISPONIBLE',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `disponibilidades`
--

INSERT INTO `disponibilidades` (`id_disponibilidad`, `id_profesional_servicio`, `fecha`, `hora_inicio`, `hora_fin`, `cupo_maximo`, `estado`, `fecha_registro`) VALUES
(1, 1, '2026-11-16', '08:00:00', '12:00:00', 1, 'DISPONIBLE', '2026-11-14 14:05:22'),
(2, 1, '2026-11-17', '08:00:00', '12:00:00', 1, 'DISPONIBLE', '2026-11-14 14:05:22'),
(3, 2, '2026-11-16', '13:00:00', '16:00:00', 1, 'DISPONIBLE', '2026-11-14 14:05:22'),
(4, 2, '2026-11-17', '13:00:00', '16:00:00', 1, 'DISPONIBLE', '2026-11-14 14:05:22'),
(5, 3, '2026-11-16', '16:00:00', '18:00:00', 5, 'DISPONIBLE', '2026-11-15 18:49:13'),
(6, 3, '2026-11-17', '16:00:00', '18:00:00', 5, 'DISPONIBLE', '2026-11-15 18:49:13'),
(7, 4, '2026-11-16', '18:00:00', '20:00:00', 1, 'DISPONIBLE', '2026-11-15 18:49:13'),
(8, 4, '2026-11-17', '18:00:00', '20:00:00', 1, 'DISPONIBLE', '2026-11-15 18:49:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_citas`
--

CREATE TABLE `historial_citas` (
  `id_historial` int(11) NOT NULL,
  `id_cita` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo_cambio` enum('SOLICITUD','CONFIRMACION','CANCELACION','REPROGRAMACION','ASISTENCIA') NOT NULL,
  `estado_anterior` varchar(30) DEFAULT NULL,
  `estado_nuevo` varchar(30) DEFAULT NULL,
  `id_disponibilidad_anterior` int(11) DEFAULT NULL,
  `id_disponibilidad_nueva` int(11) DEFAULT NULL,
  `fecha_cambio` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_citas`
--

INSERT INTO `historial_citas` (`id_historial`, `id_cita`, `id_usuario`, `tipo_cambio`, `estado_anterior`, `estado_nuevo`, `id_disponibilidad_anterior`, `id_disponibilidad_nueva`, `fecha_cambio`, `observacion`) VALUES
(1, 1, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 1, '2026-11-14 14:23:43', 'Carlos solicitó una asesoría académica'),
(2, 2, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 2, '2026-11-15 10:41:46', 'Solicitud de cita registrada por el estudiante.'),
(3, 2, 1, 'CANCELACION', 'PENDIENTE', 'CANCELADA', 2, 2, '2026-11-15 11:37:54', 'Cita cancelada por el usuario.'),
(4, 1, 1, 'REPROGRAMACION', 'PENDIENTE', 'PENDIENTE', 1, 2, '2026-11-15 12:29:57', 'Cita reprogramada para el 2026-11-17 a las 08:30:00'),
(5, 1, 2, 'CONFIRMACION', 'PENDIENTE', 'CONFIRMADA', 2, 2, '2026-11-15 13:47:41', 'Cita confirmada correctamente.'),
(6, 3, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 2, '2026-11-15 14:25:20', 'Solicitud de cita registrada por el estudiante.'),
(7, 3, 2, 'CONFIRMACION', 'PENDIENTE', 'CONFIRMADA', 2, 2, '2026-11-15 14:25:54', 'Cita confirmada correctamente.'),
(8, 4, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 2, '2026-11-15 15:08:44', 'Solicitud de cita registrada por el estudiante.'),
(9, 4, 2, 'CONFIRMACION', 'PENDIENTE', 'CONFIRMADA', 2, 2, '2026-11-15 15:09:28', 'Cita confirmada correctamente.'),
(10, 5, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 2, '2026-11-15 16:08:56', 'Solicitud de cita registrada por el estudiante.'),
(11, 6, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 2, '2026-11-15 18:04:04', 'Solicitud de cita registrada por el estudiante.'),
(12, 5, 2, 'CONFIRMACION', 'PENDIENTE', 'CONFIRMADA', 2, 2, '2026-11-15 18:05:26', 'Cita confirmada correctamente.'),
(13, 7, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 2, '2026-11-15 18:15:25', 'Solicitud de cita registrada por el estudiante.'),
(14, 7, 2, 'CONFIRMACION', 'PENDIENTE', 'CONFIRMADA', 2, 2, '2026-11-15 18:15:50', 'Cita confirmada correctamente.'),
(15, 7, 2, 'ASISTENCIA', 'CONFIRMADA', 'FINALIZADA', NULL, NULL, '2026-11-15 18:15:57', 'Se registró la asistencia del estudiante.'),
(16, 8, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 6, '2026-11-15 19:06:21', 'Solicitud de cita registrada por el estudiante.'),
(17, 9, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 6, '2026-11-15 19:06:41', 'Solicitud de cita registrada por el estudiante.'),
(18, 10, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 6, '2026-11-15 19:06:56', 'Solicitud de cita registrada por el estudiante.'),
(19, 11, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 6, '2026-11-15 19:07:08', 'Solicitud de cita registrada por el estudiante.'),
(20, 12, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 6, '2026-11-15 19:07:24', 'Solicitud de cita registrada por el estudiante.'),
(21, 13, 1, 'SOLICITUD', NULL, 'PENDIENTE', NULL, 6, '2026-11-15 19:07:43', 'Solicitud de cita registrada por el estudiante.'),
(22, 13, 1, 'CANCELACION', 'PENDIENTE', 'CANCELADA', 6, 6, '2026-11-15 19:42:50', 'Cita cancelada por el usuario.'),
(23, 8, 1, 'REPROGRAMACION', 'PENDIENTE', 'CONFIRMADA', 6, 6, '2026-11-15 19:53:09', 'Cita reprogramada para el 2026-11-17 a las 16:40:00'),
(24, 8, 1, 'REPROGRAMACION', 'CONFIRMADA', 'CONFIRMADA', 6, 6, '2026-11-15 19:54:52', 'Cita reprogramada para el 2026-11-17 a las 17:00:00'),
(25, 11, 1, 'REPROGRAMACION', 'PENDIENTE', 'CONFIRMADA', 6, 6, '2026-11-15 20:22:12', 'Cita reprogramada para el 2026-11-17 a las 17:40:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesional_servicio`
--

CREATE TABLE `profesional_servicio` (
  `id_profesional_servicio` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_asignacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `profesional_servicio`
--

INSERT INTO `profesional_servicio` (`id_profesional_servicio`, `id_usuario`, `id_servicio`, `estado`, `fecha_asignacion`) VALUES
(1, 2, 1, 'ACTIVO', '2026-11-14 14:03:24'),
(2, 2, 4, 'ACTIVO', '2026-11-14 14:03:24'),
(3, 2, 2, 'ACTIVO', '2026-11-15 18:46:05'),
(4, 2, 3, 'ACTIVO', '2026-11-15 18:46:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recordatorios`
--

CREATE TABLE `recordatorios` (
  `id_recordatorio` int(11) NOT NULL,
  `id_cita` int(11) NOT NULL,
  `fecha_programada` datetime NOT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `medio` enum('CORREO','SISTEMA') NOT NULL,
  `estado` enum('PENDIENTE','ENVIADO','ERROR') NOT NULL DEFAULT 'PENDIENTE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recordatorios`
--

INSERT INTO `recordatorios` (`id_recordatorio`, `id_cita`, `fecha_programada`, `fecha_envio`, `medio`, `estado`) VALUES
(2, 5, '2026-11-16 09:00:00', '2026-11-15 16:16:27', 'SISTEMA', 'ENVIADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`) VALUES
(1, 'ESTUDIANTE', 'Usuario que solicita citas'),
(2, 'PROFESIONAL', 'Profesional o encargado que brinda atención'),
(3, 'COORDINADOR', 'Usuario que supervisa servicios y agendas'),
(4, 'ADMINISTRADOR', 'Usuario que administra el sistema');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id_servicio` int(11) NOT NULL,
  `id_unidad` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `duracion_minutos` int(11) NOT NULL,
  `modalidad` enum('INDIVIDUAL','POR_CUPOS') NOT NULL,
  `metodo_asignacion` enum('ELEGIR','AUTOMATICA') NOT NULL,
  `metodo_confirmacion` enum('AUTOMATICA','MANUAL') NOT NULL,
  `cupo_maximo` int(11) NOT NULL DEFAULT 1,
  `anticipacion_minima_horas` int(11) NOT NULL DEFAULT 0,
  `horas_limite_cancelacion` int(11) NOT NULL DEFAULT 0,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id_servicio`, `id_unidad`, `nombre`, `descripcion`, `duracion_minutos`, `modalidad`, `metodo_asignacion`, `metodo_confirmacion`, `cupo_maximo`, `anticipacion_minima_horas`, `horas_limite_cancelacion`, `estado`) VALUES
(1, 1, 'Asesoría académica', 'Atención y orientación sobre temas académicos', 30, 'INDIVIDUAL', 'AUTOMATICA', 'MANUAL', 1, 24, 12, 'ACTIVO'),
(2, 2, 'Préstamo de equipo bibliográfico', 'Atención relacionada con servicios bibliográficos', 20, 'POR_CUPOS', 'AUTOMATICA', 'AUTOMATICA', 5, 2, 2, 'ACTIVO'),
(3, 3, 'Soporte técnico', 'Atención para resolver problemas informáticos', 30, 'INDIVIDUAL', 'ELEGIR', 'MANUAL', 1, 2, 1, 'ACTIVO'),
(4, 4, 'Atención de bienestar estudiantil', 'Atención y orientación de bienestar universitario', 45, 'INDIVIDUAL', 'AUTOMATICA', 'MANUAL', 1, 24, 12, 'ACTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades`
--

CREATE TABLE `unidades` (
  `id_unidad` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ubicacion` varchar(150) DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `unidades`
--

INSERT INTO `unidades` (`id_unidad`, `nombre`, `descripcion`, `ubicacion`, `estado`) VALUES
(1, 'Orientación Académica', 'Unidad de orientación y asesoría académica', 'Campus universitario', 'ACTIVA'),
(2, 'Biblioteca', 'Servicios de consulta y apoyo bibliográfico', 'Biblioteca universitaria', 'ACTIVA'),
(3, 'Soporte Informático', 'Atención y soporte técnico informático', 'Campus universitario', 'ACTIVA'),
(4, 'Bienestar Universitario', 'Servicios de atención y bienestar estudiantil', 'Campus universitario', 'ACTIVA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_unidad` int(11) DEFAULT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `carnet` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `id_rol`, `id_unidad`, `nombres`, `apellidos`, `correo`, `carnet`, `password`, `telefono`, `estado`, `fecha_registro`) VALUES
(1, 1, NULL, 'Carlos', 'Estudiante', 'estudiante@citaues.com', 'ES001', '$2y$10$EOF4mr016enTDOhchZKaA..UOcnBvFlExmMr1hh96iRpu61m/DC3q', '70000001', 'ACTIVO', '2026-11-14 13:55:17'),
(2, 2, 1, 'Ana', 'Profesional', 'profesional@citaues.com', NULL, '$2y$10$bV6Hc1js.77eRypGocx7V.cWz6uUVYS0.yWehK.E77UgFqMeoyfmS', '70000002', 'ACTIVO', '2026-11-14 13:55:17'),
(3, 3, 1, 'Luis', 'Coordinador', 'coordinador@citaues.com', NULL, '$2y$10$gjYBVs7g/Bn0lEYEfMw6O./aR1r.bilv1z.kE5Wfw5g93hwgq/viG', '70000003', 'ACTIVO', '2026-11-14 13:55:17'),
(4, 4, NULL, 'Maria', 'Administradora', 'admin@citaues.com', NULL, '$2y$10$OKnCNRYcBCpVwwSfG0ZOs.wtKjzzM6Pwqs4JyYqncAOSO7ZCjpJHS', '70000004', 'ACTIVO', '2026-11-14 13:55:17'),
(13, 1, NULL, 'Pedro', 'Prueba', 'pedro.prueba@citaues.com', 'ES999', '$2y$10$bjojnTssL1mkE/FkMRbZ.eJ8V9Cdcqg2Q7IILOozikZn7q9mLRICq', '70009999', 'ACTIVO', '2026-11-14 21:32:22'),
(14, 1, NULL, 'Roberto', 'Profesional', 'roberto.prueba@citaues.com', NULL, '$2y$10$Jm6zaf5SopO391SI7pLWo.Wc9S3tZDLcm6GzuOrH94MWT.2mWB/Qi', '70008888', 'ACTIVO', '2026-11-14 22:00:47'),
(15, 2, 1, 'Luis', 'Profesional', 'Luis.prueba@citaues.com', NULL, '$2y$10$wys0kY32ydYKKMX5K4DQnehMcN2GrMPtlVgbEoSvNidFJXBGbmujq', '70008888', 'ACTIVO', '2026-11-14 22:04:41');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `uq_asistencia_cita` (`id_cita`),
  ADD KEY `fk_asistencia_profesional` (`id_profesional`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`),
  ADD KEY `idx_citas_disponibilidad_estado` (`id_disponibilidad`,`estado`),
  ADD KEY `idx_citas_estudiante` (`id_estudiante`);

--
-- Indices de la tabla `disponibilidades`
--
ALTER TABLE `disponibilidades`
  ADD PRIMARY KEY (`id_disponibilidad`),
  ADD KEY `fk_disponibilidades_profesional` (`id_profesional_servicio`);

--
-- Indices de la tabla `historial_citas`
--
ALTER TABLE `historial_citas`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `fk_historial_usuario` (`id_usuario`),
  ADD KEY `fk_historial_disp_anterior` (`id_disponibilidad_anterior`),
  ADD KEY `fk_historial_disp_nueva` (`id_disponibilidad_nueva`),
  ADD KEY `idx_historial_cita` (`id_cita`),
  ADD KEY `idx_historial_fecha` (`fecha_cambio`);

--
-- Indices de la tabla `profesional_servicio`
--
ALTER TABLE `profesional_servicio`
  ADD PRIMARY KEY (`id_profesional_servicio`),
  ADD UNIQUE KEY `uq_profesional_servicio` (`id_usuario`,`id_servicio`),
  ADD KEY `fk_ps_servicio` (`id_servicio`);

--
-- Indices de la tabla `recordatorios`
--
ALTER TABLE `recordatorios`
  ADD PRIMARY KEY (`id_recordatorio`),
  ADD KEY `fk_recordatorios_cita` (`id_cita`),
  ADD KEY `idx_recordatorios_fecha_estado` (`fecha_programada`,`estado`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`),
  ADD KEY `fk_servicios_unidades` (`id_unidad`);

--
-- Indices de la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id_unidad`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD UNIQUE KEY `carnet` (`carnet`),
  ADD KEY `fk_usuarios_roles` (`id_rol`),
  ADD KEY `fk_usuarios_unidades` (`id_unidad`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id_asistencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `disponibilidades`
--
ALTER TABLE `disponibilidades`
  MODIFY `id_disponibilidad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_citas`
--
ALTER TABLE `historial_citas`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `profesional_servicio`
--
ALTER TABLE `profesional_servicio`
  MODIFY `id_profesional_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `recordatorios`
--
ALTER TABLE `recordatorios`
  MODIFY `id_recordatorio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id_unidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `fk_asistencia_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  ADD CONSTRAINT `fk_asistencia_profesional` FOREIGN KEY (`id_profesional`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `fk_citas_disponibilidad` FOREIGN KEY (`id_disponibilidad`) REFERENCES `disponibilidades` (`id_disponibilidad`),
  ADD CONSTRAINT `fk_citas_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `disponibilidades`
--
ALTER TABLE `disponibilidades`
  ADD CONSTRAINT `fk_disponibilidades_profesional` FOREIGN KEY (`id_profesional_servicio`) REFERENCES `profesional_servicio` (`id_profesional_servicio`);

--
-- Filtros para la tabla `historial_citas`
--
ALTER TABLE `historial_citas`
  ADD CONSTRAINT `fk_historial_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  ADD CONSTRAINT `fk_historial_disp_anterior` FOREIGN KEY (`id_disponibilidad_anterior`) REFERENCES `disponibilidades` (`id_disponibilidad`),
  ADD CONSTRAINT `fk_historial_disp_nueva` FOREIGN KEY (`id_disponibilidad_nueva`) REFERENCES `disponibilidades` (`id_disponibilidad`),
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `profesional_servicio`
--
ALTER TABLE `profesional_servicio`
  ADD CONSTRAINT `fk_ps_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`),
  ADD CONSTRAINT `fk_ps_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `recordatorios`
--
ALTER TABLE `recordatorios`
  ADD CONSTRAINT `fk_recordatorios_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`);

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `fk_servicios_unidades` FOREIGN KEY (`id_unidad`) REFERENCES `unidades` (`id_unidad`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `fk_usuarios_unidades` FOREIGN KEY (`id_unidad`) REFERENCES `unidades` (`id_unidad`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
