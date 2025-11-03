<?php
/**
 * Stub para que VSCode / analizadores reconozcan MongoDB\BSON\UTCDateTime
 * No afecta si la extensión ext-mongodb está instalada (se comprueba con class_exists).
 *
 * Añade métodos mínimos usados comúnmente: __construct, toDateTime, __toString, jsonSerialize.
 */

namespace MongoDB\BSON;

if (!\class_exists('MongoDB\BSON\UTCDateTime')) {
    final class UTCDateTime implements \JsonSerializable
    {
        /** @var int|null Milisegundos desde Unix epoch */
        private $milliseconds;

        /**
         * @param int|\DateTimeInterface|null $millisecondsOrDatetime
         */
        public function __construct($millisecondsOrDatetime = null)
        {
            if ($millisecondsOrDatetime instanceof \DateTimeInterface) {
                $this->milliseconds = (int) ($millisecondsOrDatetime->format('U') * 1000)
                    + (int) floor((int) $millisecondsOrDatetime->format('u') / 1000);
                return;
            }

            if ($millisecondsOrDatetime === null) {
                $this->milliseconds = (int) (microtime(true) * 1000);
                return;
            }

            $this->milliseconds = (int) $millisecondsOrDatetime;
        }

        /**
         * Convierte a DateTime (similar al método real).
         *
         * @return \DateTime
         */
        public function toDateTime(): \DateTime
        {
            $ms = $this->milliseconds ?? 0;
            $seconds = (int) floor($ms / 1000);
            $micro = (int) (($ms % 1000) * 1000);

            // Crear DateTime desde timestamp con microsegundos
            $dt = \DateTime::createFromFormat('U u', sprintf('%d %06d', $seconds, $micro));
            if ($dt === false) {
                // Fallback simple
                $dt = new \DateTime('@' . $seconds);
                $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }
            return $dt;
        }

        public function __toString(): string
        {
            return (string) ($this->milliseconds ?? '');
        }

        /**
         * Para compatibilidad con json_encode()
         *
         * @return int|null
         */
        public function jsonSerialize(): ?int
        {
            return $this->milliseconds;
        }
    }
}