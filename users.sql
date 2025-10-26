-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 29 sep. 2025 à 12:12
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `pointage`
--

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee','responsable','pdg') NOT NULL DEFAULT 'employee',
  `matiere` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `matiere`, `created_at`) VALUES
(1, 'rh', 'admin@gmail.com', '$2y$10$ME.rcd7vAeMAQT0KxISnQuXbI18geRMwNoQv9PgRfdHJHAIEcoSma', 'admin', '', '2025-08-22 13:10:15'),
(2, 'rania', 'rania@gmail.com', '$2y$10$GmUxeM6O0pFk6hqP3eykCONOA6e5qS9nHwA/.ChulLjbmz4d4YsW.', 'employee', '', '2025-08-22 13:14:01'),
(5, 'Responsable', 'responsable@gmail.com', '$2y$10$F2BWC1Bj6Qb/jtgeMkgD8OW5yAgPVnqeY.XYxzeYq4xCwQw260H9y', 'responsable', '', '2025-09-15 09:36:19'),
(6, 'oussema', 'jeljlioussema@gmail.com', '$2y$10$Uvj2V8Af8co9ibzuCRFiZOsIAQ.6koTa4VWjXSayOCpEDcLrd3YL6', 'responsable', 'Directeur qualité', '2025-09-16 09:47:15'),
(7, 'res_qualité', 'qualite@gmail.com', '$2y$10$54Wit7Xx5fwYWMaxTocPS.gTz9QCzFeNgem7UEf5YMwWfbfuyGtnO', 'employee', 'Qualité système', '2025-09-16 10:07:21'),
(9, 'PDG', 'pdg@gmail.com', '$2y$10$xYPBZjUVpgs/aPA54nWyX.SghuczDmLj.3nCIuX6YQ1WV4/kvmTI6', 'pdg', 'Direction Générale', '2025-09-27 17:15:02');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
