<?php
interface _ {}
class double {}
class CVoid {}
class size_t {}


interface libc
{

    //int printf(const char *format, ...);
    public function printf(char &$f, ...$a): int;
    //char *strcat(char *dest, const char *src);
    public function &strcat(char &$dest, &$src): char;

    //double pow(double x, double y);
    public function pow(double $x, double $y): double;

    //void srand(unsigned int seed);
    public function srand(int $seed): void;

    //void *memset(void *s, int c, size_t n);
    public function &memset( _&CVoid $s, int $c, size_t $n):void;
}

